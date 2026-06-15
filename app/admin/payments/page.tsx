import { redirect } from "next/navigation";
import { money } from "@/lib/catering";
import { requireProfile } from "@/lib/auth";
import { createClient } from "@/lib/server";

async function verifyPayment(formData: FormData) {
  "use server";

  const profile = await requireProfile();
  const supabase = await createClient();
  const paymentId = String(formData.get("payment_id") ?? "");

  const { data: payment } = await supabase
    .from("payments")
    .select("id, inquiry_id, amount, payment_type, payment_method, inquiries(id, ref_code, reference_code, customer_name, customer_email, customer_phone, event_type, event_date, event_time, guest_count, venue_name, venue_address, city, package_id)")
    .eq("id", paymentId)
    .single();

  if (!payment?.inquiry_id || !payment.inquiries) redirect("/admin/payments?error=payment");

  const inquiry = Array.isArray(payment.inquiries) ? payment.inquiries[0] : payment.inquiries;
  if (!inquiry) redirect("/admin/payments?error=payment");
  const { data: quotation } = await supabase
    .from("quotations")
    .select("id, total_amount, reservation_fee_amount, final_payment_due_at")
    .eq("inquiry_id", inquiry.id)
    .order("created_at", { ascending: false })
    .limit(1)
    .maybeSingle();

  if (payment.payment_type === "reservation_fee") {
    const { data: conflict } = await supabase
      .from("reservations")
      .select("id, reservation_code")
      .eq("event_date", inquiry.event_date)
      .eq("status", "confirmed")
      .neq("inquiry_id", inquiry.id)
      .limit(1)
      .maybeSingle();

    if (conflict) {
      await supabase.from("payments").update({ payment_status: "rejected", rejected_reason: `Date already secured by ${conflict.reservation_code}.` }).eq("id", payment.id);
      redirect("/admin/payments?error=date-taken");
    }
  }

  await supabase
    .from("payments")
    .update({
      payment_status: "verified",
      verified_by: profile.id,
      verified_at: new Date().toISOString(),
      paid_at: new Date().toISOString(),
    })
    .eq("id", payment.id);

  if (payment.payment_type === "reservation_fee") {
    const { data: existingReservation } = await supabase
      .from("reservations")
      .select("id")
      .eq("inquiry_id", inquiry.id)
      .maybeSingle();

    if (!existingReservation) {
      const { data: reservation } = await supabase
        .from("reservations")
        .insert({
          inquiry_id: inquiry.id,
          quotation_id: quotation?.id ?? null,
          customer_name: inquiry.customer_name,
          customer_email: inquiry.customer_email,
          customer_phone: inquiry.customer_phone,
          event_type: inquiry.event_type,
          event_date: inquiry.event_date,
          event_start_time: inquiry.event_time,
          guest_count: inquiry.guest_count ?? 1,
          final_pax: inquiry.guest_count ?? 1,
          venue_name: inquiry.venue_name,
          venue_address: inquiry.venue_address || "To confirm",
          city: inquiry.city || "To confirm",
          package_id: inquiry.package_id,
          status: "confirmed",
          confirmed_at: new Date().toISOString(),
          total_amount: Number(quotation?.total_amount ?? payment.amount),
          deposit_required: Number(quotation?.reservation_fee_amount ?? payment.amount),
          deposit_paid: Number(payment.amount),
          final_payment_due_at: quotation?.final_payment_due_at ?? null,
          final_payment_status: "unpaid",
        })
        .select("id, reservation_code")
        .single();

      await supabase.from("payments").update({ reservation_id: reservation?.id ?? null }).eq("id", payment.id);
      await supabase.from("inquiries").update({ status: "confirmed" }).eq("id", inquiry.id);
      if (quotation?.id) await supabase.from("quotations").update({ status: "accepted", accepted_at: new Date().toISOString() }).eq("id", quotation.id);

      await supabase.from("internal_tasks").insert([
        { inquiry_id: inquiry.id, reservation_id: reservation?.id, title: "Confirm final menu and special requirements", created_by: profile.id },
        { inquiry_id: inquiry.id, reservation_id: reservation?.id, title: "Prepare final payment reminder", created_by: profile.id, due_at: quotation?.final_payment_due_at ?? null },
      ]);
    }
  }

  await supabase.from("activity_logs").insert({
    inquiry_id: inquiry.id,
    actor_id: profile.id,
    action: "payment_verified",
    description: `Payment ${money(payment.amount)} verified.`,
  });

  redirect("/admin/payments?verified=1");
}

async function rejectPayment(formData: FormData) {
  "use server";

  const profile = await requireProfile();
  const supabase = await createClient();
  const paymentId = String(formData.get("payment_id") ?? "");
  const reason = String(formData.get("reason") ?? "Payment proof rejected.").trim();
  await supabase.from("payments").update({ payment_status: "rejected", rejected_reason: reason }).eq("id", paymentId);
  await supabase.from("activity_logs").insert({ actor_id: profile.id, action: "payment_rejected", description: reason });
  redirect("/admin/payments?rejected=1");
}

export default async function AdminPaymentsPage() {
  const supabase = await createClient();
  const { data: payments } = await supabase
    .from("payments")
    .select("id, inquiry_id, amount, payment_method, payment_status, payment_type, reference_number, proof_url, uploaded_by_customer, verified_at, rejected_reason, created_at, inquiries(ref_code, reference_code, customer_name, event_date)")
    .order("created_at", { ascending: false });

  return (
    <div className="space-y-6">
      <section className="rounded-md border border-neutral-200 bg-white p-6">
        <p className="text-xs font-medium uppercase tracking-[0.18em] text-neutral-500">Payments</p>
        <h1 className="mt-3 text-3xl font-semibold tracking-tight text-neutral-950">Payment proof verification</h1>
        <p className="mt-2 text-sm text-neutral-600">Pending proof does not confirm a reservation. Verification is the step that secures the date.</p>
      </section>

      <section className="grid gap-4 sm:grid-cols-4">
        <Metric label="Pending" value={String((payments ?? []).filter((item) => item.payment_status === "pending").length)} />
        <Metric label="Verified" value={String((payments ?? []).filter((item) => item.payment_status === "verified").length)} />
        <Metric label="Rejected" value={String((payments ?? []).filter((item) => item.payment_status === "rejected").length)} />
        <Metric label="Total collected" value={money((payments ?? []).filter((item) => item.payment_status === "verified").reduce((sum, item) => sum + Number(item.amount ?? 0), 0))} />
      </section>

      <section className="grid gap-4">
        {(payments ?? []).map((payment) => {
          const inquiry = Array.isArray(payment.inquiries) ? payment.inquiries[0] : payment.inquiries;
          return (
          <article key={payment.id} className="rounded-md border border-neutral-200 bg-white p-5">
            <div className="flex flex-col justify-between gap-3 lg:flex-row lg:items-start">
              <div>
                <p className="text-lg font-semibold text-neutral-950">{money(payment.amount)} - {payment.payment_type}</p>
                <p className="mt-1 text-sm text-neutral-500">
                  {inquiry?.reference_code || inquiry?.ref_code} - {inquiry?.customer_name} - {payment.payment_method}
                </p>
                {payment.reference_number && <p className="mt-1 text-sm text-neutral-500">Ref {payment.reference_number}</p>}
                {payment.rejected_reason && <p className="mt-2 text-sm text-red-700">{payment.rejected_reason}</p>}
              </div>
              <Status value={payment.payment_status} />
            </div>
            {payment.payment_status === "pending" && (
              <div className="mt-4 flex flex-wrap gap-3">
                <form action={verifyPayment}>
                  <input type="hidden" name="payment_id" value={payment.id} />
                  <button className="h-10 rounded-md bg-neutral-950 px-4 text-sm font-medium text-white">Verify</button>
                </form>
                <form action={rejectPayment} className="flex gap-2">
                  <input type="hidden" name="payment_id" value={payment.id} />
                  <input name="reason" placeholder="Reject reason" className="h-10 rounded-md border border-neutral-300 px-3 text-sm" />
                  <button className="h-10 rounded-md border border-neutral-300 px-4 text-sm font-medium">Reject</button>
                </form>
              </div>
            )}
          </article>
          );
        })}
      </section>
    </div>
  );
}

function Metric({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-md border border-neutral-200 bg-white p-5">
      <p className="text-sm text-neutral-500">{label}</p>
      <p className="mt-2 text-2xl font-semibold text-neutral-950">{value}</p>
    </div>
  );
}

function Status({ value }: { value: string }) {
  return <span className="rounded-full border border-neutral-300 px-2.5 py-1 text-xs capitalize text-neutral-700">{value.replaceAll("_", " ")}</span>;
}
