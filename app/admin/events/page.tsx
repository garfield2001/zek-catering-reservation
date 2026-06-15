import { createClient } from "@/lib/server";

export default async function AdminEventsPage() {
  const supabase = await createClient();
  const { data: reservations } = await supabase
    .from("reservations")
    .select("reservation_code, customer_name, event_type, event_date, event_start_time, guest_count, venue_name, city, status")
    .order("event_date", { ascending: true });

  const events = reservations ?? [];
  const confirmed = events.filter((item) => item.status === "confirmed");
  const needsOps = events.filter((item) => ["draft", "tentative"].includes(item.status));

  return (
    <div className="space-y-6">
      <section className="rounded-md border border-neutral-200 bg-white p-6">
        <p className="text-xs font-medium uppercase tracking-[0.18em] text-neutral-500">Events</p>
        <h1 className="mt-3 text-3xl font-semibold tracking-tight text-neutral-950">Event production</h1>
        <p className="mt-2 max-w-2xl text-sm leading-6 text-neutral-600">
          Events are the operational side of reservations: delivery date, call time, venue, headcount, staffing, equipment, and service execution.
        </p>
      </section>

      <section className="grid gap-4 md:grid-cols-3">
        <Metric label="Scheduled events" value={String(events.length)} />
        <Metric label="Confirmed" value={String(confirmed.length)} />
        <Metric label="Needs ops review" value={String(needsOps.length)} />
      </section>

      <section className="overflow-hidden rounded-md border border-neutral-200 bg-white">
        <div className="border-b border-neutral-200 p-4">
          <h2 className="text-lg font-semibold text-neutral-950">Production calendar</h2>
        </div>
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-neutral-200 text-sm">
            <thead className="bg-neutral-50 text-left text-xs uppercase tracking-[0.12em] text-neutral-500">
              <tr>
                {["Code", "Event", "Client", "Date", "Time", "Guests", "Venue", "Status"].map((column) => (
                  <th key={column} className="px-4 py-3 font-medium">
                    {column}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-200">
              {events.length === 0 ? (
                <tr>
                  <td colSpan={8} className="px-4 py-8 text-center text-neutral-500">
                    No reservations have become events yet.
                  </td>
                </tr>
              ) : (
                events.map((item) => (
                  <tr key={item.reservation_code} className="hover:bg-neutral-50">
                    <td className="whitespace-nowrap px-4 py-4 font-medium text-neutral-950">{item.reservation_code}</td>
                    <td className="whitespace-nowrap px-4 py-4 text-neutral-700">{item.event_type}</td>
                    <td className="whitespace-nowrap px-4 py-4 text-neutral-700">{item.customer_name}</td>
                    <td className="whitespace-nowrap px-4 py-4 text-neutral-700">{item.event_date}</td>
                    <td className="whitespace-nowrap px-4 py-4 text-neutral-700">{item.event_start_time ?? "TBD"}</td>
                    <td className="whitespace-nowrap px-4 py-4 text-neutral-700">{item.guest_count}</td>
                    <td className="whitespace-nowrap px-4 py-4 text-neutral-700">{item.venue_name ?? item.city}</td>
                    <td className="whitespace-nowrap px-4 py-4 text-neutral-700 capitalize">{item.status.replaceAll("_", " ")}</td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
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
