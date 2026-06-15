import { money } from "@/lib/catering";
import { createClient } from "@/lib/server";

export default async function AdminReportsPage() {
  const supabase = await createClient();
  const [{ data: inquiries }, { data: reservations }, { data: payments }, { data: feedback }] = await Promise.all([
    supabase.from("inquiries").select("status, event_date"),
    supabase.from("reservations").select("status, event_date, total_amount, deposit_paid, final_payment_status"),
    supabase.from("payments").select("amount, payment_status, payment_type"),
    supabase.from("feedback").select("rating"),
  ]);

  const verifiedPayments = (payments ?? []).filter((item) => item.payment_status === "verified");
  const avgFeedback =
    feedback && feedback.length > 0
      ? feedback.reduce((sum, item) => sum + Number(item.rating ?? 0), 0) / feedback.length
      : 0;

  return (
    <div className="space-y-6">
      <section className="rounded-md border border-neutral-200 bg-white p-6">
        <p className="text-xs font-medium uppercase tracking-[0.18em] text-neutral-500">Reports</p>
        <h1 className="mt-3 text-3xl font-semibold tracking-tight text-neutral-950">Operational reporting</h1>
        <p className="mt-2 text-sm text-neutral-600">Pipeline, payment, event, cancellation, and feedback metrics from Supabase.</p>
      </section>

      <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <Metric label="Inquiries" value={String(inquiries?.length ?? 0)} />
        <Metric label="Confirmed events" value={String((reservations ?? []).filter((item) => item.status === "confirmed").length)} />
        <Metric label="Verified payments" value={money(verifiedPayments.reduce((sum, item) => sum + Number(item.amount ?? 0), 0))} />
        <Metric label="Avg feedback" value={avgFeedback ? avgFeedback.toFixed(1) : "0.0"} />
      </section>

      <section className="grid gap-6 lg:grid-cols-2">
        <Panel title="Inquiry statuses" rows={countBy(inquiries ?? [], "status")} />
        <Panel title="Reservation statuses" rows={countBy(reservations ?? [], "status")} />
      </section>
    </div>
  );
}

function countBy<T extends Record<string, unknown>>(items: T[], key: keyof T) {
  const counts = new Map<string, number>();
  for (const item of items) {
    const value = String(item[key] ?? "unknown");
    counts.set(value, (counts.get(value) ?? 0) + 1);
  }
  return Array.from(counts.entries()).map(([label, value]) => ({ label, value }));
}

function Metric({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-md border border-neutral-200 bg-white p-5">
      <p className="text-sm text-neutral-500">{label}</p>
      <p className="mt-2 text-2xl font-semibold text-neutral-950">{value}</p>
    </div>
  );
}

function Panel({ title, rows }: { title: string; rows: Array<{ label: string; value: number }> }) {
  return (
    <div className="rounded-md border border-neutral-200 bg-white p-5">
      <h2 className="text-lg font-semibold text-neutral-950">{title}</h2>
      <div className="mt-4 space-y-3">
        {rows.map((row) => (
          <div key={row.label} className="flex items-center justify-between rounded-md border border-neutral-200 px-3 py-2 text-sm">
            <span className="capitalize">{row.label.replaceAll("_", " ")}</span>
            <span className="font-medium">{row.value}</span>
          </div>
        ))}
      </div>
    </div>
  );
}
