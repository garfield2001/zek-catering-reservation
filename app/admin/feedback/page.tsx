import { createClient } from "@/lib/server";

export default async function AdminFeedbackPage() {
  const supabase = await createClient();
  const { data: feedback } = await supabase
    .from("feedback")
    .select("rating, food_quality_rating, service_quality_rating, overall_experience_rating, comments, submitted_at, reservations(reservation_code, customer_name)")
    .order("submitted_at", { ascending: false });

  const average =
    feedback && feedback.length > 0
      ? feedback.reduce((sum, item) => sum + Number(item.rating ?? 0), 0) / feedback.length
      : 0;

  return (
    <div className="space-y-6">
      <section className="rounded-md border border-neutral-200 bg-white p-6">
        <p className="text-xs font-medium uppercase tracking-[0.18em] text-neutral-500">Feedback</p>
        <h1 className="mt-3 text-3xl font-semibold tracking-tight text-neutral-950">Completed-event feedback</h1>
        <p className="mt-2 text-sm text-neutral-600">Customers can submit feedback after staff marks the reservation completed.</p>
      </section>

      <section className="grid gap-4 sm:grid-cols-2">
        <Metric label="Average rating" value={average ? average.toFixed(1) : "0.0"} />
        <Metric label="Total feedback" value={String(feedback?.length ?? 0)} />
      </section>

      <section className="grid gap-4">
        {(feedback ?? []).map((item) => {
          const reservation = Array.isArray(item.reservations) ? item.reservations[0] : item.reservations;
          return (
          <article key={`${item.submitted_at}-${item.rating}`} className="rounded-md border border-neutral-200 bg-white p-5">
            <div className="flex items-center justify-between gap-3">
              <h2 className="text-lg font-semibold text-neutral-950">{reservation?.customer_name ?? "Customer"}</h2>
              <span className="rounded-full border border-neutral-300 px-3 py-1 text-sm">{item.rating}/5</span>
            </div>
            <p className="mt-3 text-sm leading-6 text-neutral-600">{item.comments ?? "No comments."}</p>
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
      <p className="mt-2 text-3xl font-semibold text-neutral-950">{value}</p>
    </div>
  );
}
