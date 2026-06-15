import { createClient } from "@/lib/server";

export default async function AdminCalendarPage() {
  const supabase = await createClient();
  const { data: reservations } = await supabase
    .from("reservations")
    .select("reservation_code, customer_name, event_type, event_date, event_start_time, status, final_pax")
    .order("event_date", { ascending: true });

  const grouped = new Map<string, typeof reservations>();
  for (const item of reservations ?? []) {
    grouped.set(item.event_date, [...(grouped.get(item.event_date) ?? []), item]);
  }

  return (
    <div className="space-y-6">
      <section className="rounded-md border border-neutral-200 bg-white p-6">
        <p className="text-xs font-medium uppercase tracking-[0.18em] text-neutral-500">Calendar</p>
        <h1 className="mt-3 text-3xl font-semibold tracking-tight text-neutral-950">Confirmed event calendar</h1>
        <p className="mt-2 text-sm text-neutral-600">Inquiries and quotations do not block dates. Confirmed reservations appear here after verified reservation fee.</p>
      </section>

      <section className="grid gap-4 lg:grid-cols-2">
        {Array.from(grouped.entries()).map(([date, items]) => (
          <article key={date} className="rounded-md border border-neutral-200 bg-white p-5">
            <h2 className="text-lg font-semibold text-neutral-950">{date}</h2>
            <div className="mt-4 space-y-3">
              {(items ?? []).map((item) => (
                <div key={item.reservation_code} className="rounded-md border border-neutral-200 p-3 text-sm">
                  <div className="flex items-center justify-between gap-3">
                    <p className="font-medium text-neutral-950">{item.customer_name}</p>
                    <span className="rounded-full border border-neutral-300 px-2 py-1 text-xs capitalize">{item.status}</span>
                  </div>
                  <p className="mt-1 text-neutral-500">{item.event_type} - {item.final_pax} pax - {item.event_start_time ?? "time TBD"}</p>
                </div>
              ))}
            </div>
          </article>
        ))}
      </section>
    </div>
  );
}
