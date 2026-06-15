import Link from "next/link";
import { redirect } from "next/navigation";
import { addDays, calculateQuotation, money } from "@/lib/catering";
import { requireProfile } from "@/lib/auth";
import { createClient } from "@/lib/server";

async function createQuotation(formData: FormData) {
  "use server";

  const profile = await requireProfile();
  const supabase = await createClient();
  const inquiryId = String(formData.get("inquiry_id") ?? "");
  const transportationFee = Number(formData.get("transportation_fee") ?? 0) || 0;
  const transportationReason = String(formData.get("transportation_reason") ?? "").trim();
  const emergencyFee = Number(formData.get("emergency_fee") ?? 0) || 0;
  const discount = Number(formData.get("discount") ?? 0) || 0;

  if (!inquiryId || (transportationFee > 0 && !transportationReason)) {
    redirect("/admin/quotations?error=missing");
  }

  const [{ data: inquiry }, { data: settings }] = await Promise.all([
    supabase
      .from("inquiries")
      .select("id, ref_code, reference_code, event_date, guest_count, package_id, customer_name, catering_packages(id, name, price_per_pax, minimum_pax, pax_increment, meal_slots, drink_slots), inquiry_menu_selections(item_type, item_id, snapshot_name, snapshot_price)")
      .eq("id", inquiryId)
      .single(),
    supabase.from("business_settings").select("*").limit(1).single(),
  ]);

  if (!inquiry || !inquiry.catering_packages || !inquiry.guest_count) {
    redirect("/admin/quotations?error=inquiry");
  }
  const packageData = Array.isArray(inquiry.catering_packages) ? inquiry.catering_packages[0] : inquiry.catering_packages;
  if (!packageData) redirect("/admin/quotations?error=package");
  const selected = (inquiry.inquiry_menu_selections ?? []) as Array<{
    item_type: string;
    item_id: string | null;
    snapshot_name: string;
    snapshot_price: number;
  }>;

  const packageRule = {
    id: packageData.id,
    name: packageData.name,
    price_per_pax: Number(packageData.price_per_pax),
    minimum_pax: packageData.minimum_pax,
    pax_increment: packageData.pax_increment,
    meal_slots: packageData.meal_slots,
    drink_slots: packageData.drink_slots,
  };
  const pax = Number(inquiry.guest_count);
  const premiumSelections = selected.filter((item) => Number(item.snapshot_price ?? 0) > 0);
  const manualCharges = transportationFee + emergencyFee;
  const quote = calculateQuotation({
    package: packageRule,
    pax,
    dishes: premiumSelections.map((item) => ({
      id: item.item_id ?? item.snapshot_name,
      name: item.snapshot_name,
      dish_type: "premium",
      premium_pricing_mode: "per_pax",
      premium_price: Number(item.snapshot_price),
    })),
    transportationFee,
    emergencyFee,
    discount,
    reservationFeeType: settings?.reservation_fee_type ?? "fixed",
    reservationFeeValue: Number(settings?.reservation_fee_value ?? 5000),
  });

  const now = new Date();
  const reservationFeeDue = addDays(now, Number(settings?.reservation_fee_due_days ?? 3));
  const validUntil = addDays(now, Number(settings?.quotation_validity_days ?? 3));
  const finalDue = addDays(new Date(`${inquiry.event_date}T00:00:00`), -Number(settings?.final_payment_due_days ?? 7));

  const { data: quotation, error } = await supabase
    .from("quotations")
    .insert({
      inquiry_id: inquiry.id,
      package_id: packageRule.id,
      pax,
      status: "sent",
      subtotal: quote.subtotal,
      discount_amount: discount,
      total_amount: quote.total,
      reservation_fee_type: settings?.reservation_fee_type ?? "fixed",
      reservation_fee_value: Number(settings?.reservation_fee_value ?? 5000),
      reservation_fee_amount: quote.reservationFee,
      reservation_fee_due_at: reservationFeeDue.toISOString(),
      final_payment_due_at: finalDue.toISOString(),
      valid_until: validUntil.toISOString(),
      terms_snapshot: settings?.cancellation_policy_text ?? "Reservation fee is non-refundable.",
      sent_at: now.toISOString(),
      created_by: profile.id,
    })
    .select("id, quotation_number")
    .single();

  if (error || !quotation) {
    redirect(`/admin/quotations?error=${encodeURIComponent(error?.message ?? "quote")}`);
  }

  const items = [
    { line_type: "package", description: `${packageRule.name} buffet package`, quantity: pax, unit_price: packageRule.price_per_pax, amount: quote.base, sort_order: 10 },
    ...premiumSelections.map((item, index) => ({
      line_type: "premium",
      description: `Premium menu: ${item.snapshot_name}`,
      quantity: pax,
      unit_price: Number(item.snapshot_price),
      amount: Number(item.snapshot_price) * pax,
      sort_order: 20 + index,
    })),
    ...(transportationFee > 0
      ? [{ line_type: "transportation", description: "Transportation fee", quantity: 1, unit_price: transportationFee, amount: transportationFee, customer_visible_reason: transportationReason, sort_order: 80 }]
      : []),
    ...(emergencyFee > 0
      ? [{ line_type: "emergency", description: "Emergency/rush booking fee", quantity: 1, unit_price: emergencyFee, amount: emergencyFee, customer_visible_reason: "Urgent booking review and scheduling.", sort_order: 85 }]
      : []),
    ...(discount > 0
      ? [{ line_type: "discount", description: "Discount", quantity: 1, unit_price: -discount, amount: -discount, sort_order: 90 }]
      : []),
  ];

  await supabase.from("quotation_items").insert(items.map((item) => ({ ...item, quotation_id: quotation.id })));
  await supabase.from("quotation_menu_selections").insert(
    selected
      .filter((item) => ["dish", "drink"].includes(item.item_type))
      .map((item, index) => ({
        quotation_id: quotation.id,
        selection_type: item.item_type === "drink" ? "drink" : "meal",
        item_id: item.item_id,
        item_name_snapshot: item.snapshot_name,
        is_premium_snapshot: Number(item.snapshot_price ?? 0) > 0,
        premium_amount_snapshot: Number(item.snapshot_price ?? 0),
        sort_order: index,
      })),
  );
  await supabase.from("inquiries").update({ status: "awaiting_reservation_fee", updated_at: now.toISOString() }).eq("id", inquiry.id);
  await supabase.from("activity_logs").insert({
    inquiry_id: inquiry.id,
    actor_id: profile.id,
    action: "quotation_sent",
    description: `Quotation ${quotation.quotation_number} sent for ${money(quote.total)}.`,
    new_values: { quotationId: quotation.id, total: quote.total, reservationFee: quote.reservationFee, manualCharges },
  });

  redirect(`/admin/quotations/${quotation.id}`);
}

export default async function AdminQuotationsPage() {
  const supabase = await createClient();
  const [{ data: inquiries }, { data: quotations }] = await Promise.all([
    supabase
      .from("inquiries")
      .select("id, ref_code, reference_code, customer_name, event_date, guest_count, status, catering_packages(name)")
      .not("package_id", "is", null)
      .order("created_at", { ascending: false }),
    supabase
      .from("quotations")
      .select("id, quotation_number, status, total_amount, reservation_fee_amount, reservation_fee_due_at, created_at, inquiries(ref_code, reference_code, customer_name)")
      .order("created_at", { ascending: false }),
  ]);

  return (
    <div className="space-y-6">
      <section className="rounded-md border border-neutral-200 bg-white p-6">
        <p className="text-xs font-medium uppercase tracking-[0.18em] text-neutral-500">Quotations</p>
        <h1 className="mt-3 text-3xl font-semibold tracking-tight text-neutral-950">Build and snapshot quotation details</h1>
        <p className="mt-2 text-sm text-neutral-600">Finalize menu, premium charges, fees, discounts, deadlines, and policy snapshots before reservation fee payment.</p>
      </section>

      <form action={createQuotation} className="grid gap-4 rounded-md border border-neutral-200 bg-white p-5 lg:grid-cols-5">
        <label className="text-sm font-medium text-neutral-800 lg:col-span-2">
          Inquiry
          <select name="inquiry_id" className="mt-2 h-11 w-full rounded-md border border-neutral-300 px-3 text-sm" required>
            <option value="">Select inquiry</option>
            {(inquiries ?? []).map((item) => {
              const packageInfo = Array.isArray(item.catering_packages) ? item.catering_packages[0] : item.catering_packages;
              return (
                <option key={item.id} value={item.id}>
                  {item.reference_code || item.ref_code} - {item.customer_name} - {packageInfo?.name ?? "package"}
                </option>
              );
            })}
          </select>
        </label>
        <Field name="transportation_fee" label="Transport fee" type="number" />
        <Field name="emergency_fee" label="Rush fee" type="number" />
        <Field name="discount" label="Discount" type="number" />
        <label className="text-sm font-medium text-neutral-800 lg:col-span-4">
          Customer-visible transportation reason
          <input name="transportation_reason" className="mt-2 h-11 w-full rounded-md border border-neutral-300 px-3 text-sm" />
        </label>
        <button className="mt-7 h-11 rounded-md bg-neutral-950 px-4 text-sm font-medium text-white">Create quotation</button>
      </form>

      <section className="overflow-hidden rounded-md border border-neutral-200 bg-white">
        <div className="border-b border-neutral-200 p-4">
          <h2 className="text-lg font-semibold text-neutral-950">Quotation history</h2>
        </div>
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-neutral-200 text-sm">
            <thead className="bg-neutral-50 text-left text-xs uppercase tracking-[0.12em] text-neutral-500">
              <tr>{["Quote", "Customer", "Total", "Reservation fee", "Due", "Status", "Open"].map((column) => <th key={column} className="px-4 py-3 font-medium">{column}</th>)}</tr>
            </thead>
            <tbody className="divide-y divide-neutral-200">
              {(quotations ?? []).map((item) => {
                const inquiryInfo = Array.isArray(item.inquiries) ? item.inquiries[0] : item.inquiries;
                return (
                <tr key={item.id}>
                  <td className="whitespace-nowrap px-4 py-4 font-medium text-neutral-950">{item.quotation_number}</td>
                  <td className="whitespace-nowrap px-4 py-4 text-neutral-700">{inquiryInfo?.customer_name}</td>
                  <td className="whitespace-nowrap px-4 py-4 text-neutral-700">{money(item.total_amount)}</td>
                  <td className="whitespace-nowrap px-4 py-4 text-neutral-700">{money(item.reservation_fee_amount)}</td>
                  <td className="whitespace-nowrap px-4 py-4 text-neutral-700">{item.reservation_fee_due_at ?? "TBD"}</td>
                  <td className="whitespace-nowrap px-4 py-4 text-neutral-700">{item.status}</td>
                  <td className="whitespace-nowrap px-4 py-4"><Link href={`/admin/quotations/${item.id}`} className="font-medium text-neutral-950 underline underline-offset-4">Details</Link></td>
                </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </section>
    </div>
  );
}

function Field({ label, name, type = "text" }: { label: string; name: string; type?: string }) {
  return (
    <label className="text-sm font-medium text-neutral-800">
      {label}
      <input name={name} type={type} min={type === "number" ? "0" : undefined} className="mt-2 h-11 w-full rounded-md border border-neutral-300 px-3 text-sm" />
    </label>
  );
}
