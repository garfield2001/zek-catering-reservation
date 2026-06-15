import { createClient } from "@/lib/server";

export default async function AdminTasksPage() {
  const supabase = await createClient();
  const { data: tasks } = await supabase
    .from("internal_tasks")
    .select("id, title, description, status, due_at, created_at, reservations(reservation_code, customer_name), inquiries(ref_code, reference_code, customer_name)")
    .order("due_at", { ascending: true, nullsFirst: false });

  return (
    <div className="space-y-6">
      <section className="rounded-md border border-neutral-200 bg-white p-6">
        <p className="text-xs font-medium uppercase tracking-[0.18em] text-neutral-500">Tasks</p>
        <h1 className="mt-3 text-3xl font-semibold tracking-tight text-neutral-950">Internal preparation tasks</h1>
        <p className="mt-2 text-sm text-neutral-600">Tasks are created when reservation payments are verified and can be expanded for operations later.</p>
      </section>

      <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        {(tasks ?? []).map((task) => (
          <article key={task.id} className="rounded-md border border-neutral-200 bg-white p-5">
            <div className="flex items-start justify-between gap-3">
              <h2 className="text-lg font-semibold text-neutral-950">{task.title}</h2>
              <span className="rounded-full border border-neutral-300 px-2.5 py-1 text-xs capitalize text-neutral-700">{task.status.replaceAll("_", " ")}</span>
            </div>
            <p className="mt-3 text-sm text-neutral-600">{task.description ?? "No description."}</p>
            <p className="mt-4 text-xs uppercase tracking-[0.12em] text-neutral-500">Due</p>
            <p className="mt-1 text-sm font-medium text-neutral-900">{task.due_at ?? "No due date"}</p>
          </article>
        ))}
      </section>
    </div>
  );
}
