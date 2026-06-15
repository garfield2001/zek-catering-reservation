import { notFound } from "next/navigation";
import { money } from "@/lib/catering";
import { createClient } from "@/lib/server";

export default async function AdminQuotationDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const supabase = await createClient();
  const { data: quotation } = await supabase
    .from("quotations")
    .select("*, inquiries(ref_code, reference_code, customer_name, customer_phone, event_date), quotation_items(line_type, description, quantity, unit_price, amount, customer_visible_reason), quotation_menu_selections(selection_type, item_name_snapshot, is_premium_snapshot, premium_amount_snapshot)")
    .eq("id", id)
    .single();

  if (!quotation) notFound();
  const inquiry = Array.isArray(quotation.inquiries) ? quotation.inquiries[0] : quotation.inquiries;
  const lineItems = (quotation.quotation_items ?? []) as Array<{
    line_type: string;
    description: string;
    amount: number;
    customer_visible_reason: string | null;
  }>;
  const menuSelections = (quotation.quotation_menu_selections ?? []) as Array<{
    selection_type: string;
    item_name_snapshot: string;
    is_premium_snapshot: boolean;
    premium_amount_snapshot: number;
  }>;

  return (
    <div className="space-y-6">
      <section className="rounded-md border border-neutral-200 bg-white p-6">
        <p className="text-xs font-medium uppercase tracking-[0.18em] text-neutral-500">Quotation</p>
        <h1 className="mt-3 text-3xl font-semibold tracking-tight text-neutral-950">{quotation.quotation_number}</h1>
        <p className="mt-2 text-sm text-neutral-600">
          {inquiry?.customer_name} - {inquiry?.reference_code || inquiry?.ref_code}
        </p>
      </section>

      <section className="grid gap-6 lg:grid-cols-4">
        <Metric label="Total" value={money(quotation.total_amount)} />
        <Metric label="Reservation fee" value={money(quotation.reservation_fee_amount)} />
        <Metric label="Fee due" value={quotation.reservation_fee_due_at ?? "TBD"} />
        <Metric label="Final due" value={quotation.final_payment_due_at ?? "TBD"} />
      </section>

      <section className="grid gap-6 lg:grid-cols-[1fr_0.75fr]">
        <Panel title="Line items">
          {lineItems.map((item) => (
            <div key={`${item.line_type}-${item.description}`} className="rounded-md border border-neutral-200 p-3 text-sm">
              <div className="flex items-center justify-between gap-3">
                <span>{item.description}</span>
                <span className="font-medium">{money(item.amount)}</span>
              </div>
              {item.customer_visible_reason && <p className="mt-2 text-neutral-500">{item.customer_visible_reason}</p>}
            </div>
          ))}
        </Panel>
        <Panel title="Menu snapshot">
          {menuSelections.map((item) => (
            <div key={`${item.selection_type}-${item.item_name_snapshot}`} className="rounded-md border border-neutral-200 p-3 text-sm">
              <p className="font-medium capitalize text-neutral-950">{item.selection_type}: {item.item_name_snapshot}</p>
              {item.is_premium_snapshot && <p className="mt-1 text-neutral-500">Premium {money(item.premium_amount_snapshot)}</p>}
            </div>
          ))}
        </Panel>
      </section>
    </div>
  );
}

function Metric({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-md border border-neutral-200 bg-white p-5">
      <p className="text-sm text-neutral-500">{label}</p>
      <p className="mt-2 text-lg font-semibold text-neutral-950">{value}</p>
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
