import { notFound } from "next/navigation";
import Link from "next/link";
import { money } from "@/lib/catering";
import { createClient } from "@/lib/server";

export default async function AdminInquiryDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const supabase = await createClient();
  const [{ data: inquiry }, { data: quotations }, { data: payments }, { data: logs }] = await Promise.all([
    supabase
      .from("inquiries")
      .select("*, catering_packages(name, price_per_pax, meal_slots, drink_slots), inquiry_menu_selections(item_type, snapshot_name, snapshot_price), inquiry_special_requirements(requirement_type, notes)")
      .eq("id", id)
      .single(),
    supabase.from("quotations").select("id, quotation_number, status, total_amount, reservation_fee_amount, created_at").eq("inquiry_id", id).order("created_at", { ascending: false }),
    supabase.from("payments").select("amount, payment_method, payment_status, payment_type, created_at").eq("inquiry_id", id).order("created_at", { ascending: false }),
    supabase.from("activity_logs").select("action, description, created_at").eq("inquiry_id", id).order("created_at", { ascending: false }).limit(10),
  ]);

  if (!inquiry) notFound();
  const packageInfo = Array.isArray(inquiry.catering_packages) ? inquiry.catering_packages[0] : inquiry.catering_packages;
  const selections = (inquiry.inquiry_menu_selections ?? []) as Array<{ item_type: string; snapshot_name: string; snapshot_price: number }>;
  const quotationRows = (quotations ?? []) as Array<{ id: string; quotation_number: string; status: string; total_amount: number; reservation_fee_amount: number }>;
  const paymentRows = (payments ?? []) as Array<{ amount: number; payment_method: string; payment_status: string; payment_type: string; created_at: string }>;
  const logRows = (logs ?? []) as Array<{ action: string; description: string | null; created_at: string }>;
  const status = statusMeta(inquiry.status);

  return (
    <div className="space-y-6">
      <section className="rounded-md border border-neutral-200 bg-white p-6">
        <p className="text-xs font-medium uppercase tracking-[0.18em] text-neutral-500">Inquiry detail</p>
        <div className="mt-3 flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
          <div>
            <div className="flex flex-wrap items-center gap-3">
              <h1 className="text-3xl font-semibold tracking-tight text-neutral-950">{inquiry.reference_code || inquiry.ref_code}</h1>
              <span className={status.className}>{status.label}</span>
            </div>
            <p className="mt-2 text-sm text-neutral-600">{inquiry.customer_name} - {inquiry.customer_phone}</p>
          </div>
          <Link href="/admin/quotations" className="h-10 rounded-md bg-neutral-950 px-4 py-2 text-sm font-medium text-white">Build quotation</Link>
        </div>
      </section>

      <section className="rounded-md border border-amber-200 bg-amber-50 p-5">
        <p className="text-sm font-semibold text-amber-950">Next staff action</p>
        <p className="mt-2 text-sm leading-6 text-amber-950">{status.guidance}</p>
      </section>

      <section className="grid gap-6 lg:grid-cols-[1fr_0.9fr]">
        <Panel title="Customer and event">
          <Info label="Status" value={inquiry.status} />
          <Info label="Event" value={`${inquiry.event_type} on ${inquiry.event_date}`} />
          <Info label="Pax" value={String(inquiry.guest_count ?? "TBD")} />
          <Info label="Package" value={packageInfo?.name ?? "TBD"} />
          <Info label="Venue" value={[inquiry.venue_name, inquiry.venue_address, inquiry.city].filter(Boolean).join(", ") || "TBD"} />
          <Info label="Customer notes" value={inquiry.customer_notes ?? inquiry.notes ?? "None"} />
        </Panel>
        <Panel title="Internal notes">
          <p className="whitespace-pre-wrap text-sm leading-6 text-neutral-700">{inquiry.internal_notes || "No internal notes yet."}</p>
        </Panel>
      </section>

      <section className="grid gap-6 lg:grid-cols-3">
        <Panel title="Selections">
          {selections.map((item) => (
            <Info key={`${item.item_type}-${item.snapshot_name}`} label={item.item_type} value={`${item.snapshot_name}${Number(item.snapshot_price) > 0 ? ` - ${money(item.snapshot_price)}` : ""}`} />
          ))}
        </Panel>
        <Panel title="Quotations">
          {quotationRows.map((item) => (
            <Info key={item.id} label={item.quotation_number} value={`${item.status} - ${money(item.total_amount)} fee ${money(item.reservation_fee_amount)}`} />
          ))}
        </Panel>
        <Panel title="Payments">
          {paymentRows.map((item) => (
            <Info key={`${item.created_at}-${item.amount}`} label={item.payment_type} value={`${money(item.amount)} ${item.payment_method} - ${item.payment_status}`} />
          ))}
        </Panel>
      </section>

      <Panel title="Activity">
        {logRows.map((log) => (
          <Info key={`${log.created_at}-${log.action}`} label={log.action} value={log.description ?? log.created_at} />
        ))}
      </Panel>
    </div>
  );
}

function Panel({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div className="rounded-md border border-neutral-200 bg-white p-5">
      <h2 className="text-lg font-semibold text-neutral-950">{title}</h2>
      <div className="mt-4 space-y-3">{children}</div>
    </div>
  );
}

function statusMeta(value: string) {
  const label = value.replaceAll("_", " ");

  if (["new", "new_inquiry"].includes(value)) {
    return {
      label,
      guidance: "Review the customer details, package, selected food, date, venue, and notes. If details are enough, build a quotation. If something is unclear, contact the customer first.",
      className: "rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-xs capitalize text-blue-700",
    };
  }

  if (["quoted", "quotation_sent"].includes(value)) {
    return {
      label,
      guidance: "A quotation is already prepared or sent. Monitor customer response and update payment or reservation records when they accept.",
      className: "rounded-full border border-violet-200 bg-violet-50 px-2.5 py-1 text-xs capitalize text-violet-700",
    };
  }

  if (value === "awaiting_reservation_fee") {
    return {
      label,
      guidance: "Customer is expected to pay the reservation fee. Check payment proof, verify it, then convert the inquiry into a confirmed reservation.",
      className: "rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs capitalize text-amber-700",
    };
  }

  if (["converted", "confirmed"].includes(value)) {
    return {
      label,
      guidance: "This inquiry has moved into the reservation stage. Use reservations, payments, and tasks to continue event preparation.",
      className: "rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs capitalize text-emerald-700",
    };
  }

  if (["cancelled", "closed_lost", "declined"].includes(value)) {
    return {
      label,
      guidance: "This inquiry is closed. Keep it for record review; no active staff action is required unless the customer reopens the request.",
      className: "rounded-full border border-neutral-200 bg-neutral-100 px-2.5 py-1 text-xs capitalize text-neutral-600",
    };
  }

  return {
    label,
    guidance: "Open the customer record, confirm the current status, and continue with the next suitable admin step.",
    className: "rounded-full border border-neutral-300 px-2.5 py-1 text-xs capitalize text-neutral-700",
  };
}

function Info({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <p className="text-xs uppercase tracking-[0.12em] text-neutral-500">{label.replaceAll("_", " ")}</p>
      <p className="mt-1 text-sm font-medium text-neutral-900">{value}</p>
    </div>
  );
}
