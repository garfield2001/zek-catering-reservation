import Link from "next/link";
import { CalendarDays, CreditCard, FileText, Inbox, Star, Users } from "lucide-react";
import { money } from "@/lib/catering";
import { createClient } from "@/lib/server";

export default async function AdminDashboardPage() {
  const supabase = await createClient();
  const [{ data: inquiries }, { data: reservations }, { data: payments }, { data: feedback }, { data: tasks }] =
    await Promise.all([
      supabase.from("inquiries").select("id, ref_code, reference_code, customer_name, event_type, event_date, status, created_at").order("created_at", { ascending: false }),
      supabase.from("reservations").select("id, reservation_code, customer_name, event_type, event_date, total_amount, deposit_paid, status, final_payment_status").order("event_date", { ascending: true }),
      supabase.from("payments").select("amount, payment_status, payment_type"),
      supabase.from("feedback").select("rating"),
      supabase.from("internal_tasks").select("status, due_at"),
    ]);

  const now = new Date();
  const nextWeek = new Date(now.getTime() + 7 * 24 * 60 * 60 * 1000);
  const upcoming = (reservations ?? []).filter((item) => new Date(item.event_date) >= now);
  const eventsThisWeek = upcoming.filter((item) => new Date(item.event_date) <= nextWeek);
  const avgFeedback =
    feedback && feedback.length > 0
      ? feedback.reduce((sum, item) => sum + Number(item.rating ?? 0), 0) / feedback.length
      : 0;

  const stats = [
    { label: "New inquiries", value: String((inquiries ?? []).filter((item) => ["new", "new_inquiry"].includes(item.status)).length), detail: "fresh leads", icon: Inbox },
    { label: "Awaiting fee", value: String((inquiries ?? []).filter((item) => item.status === "awaiting_reservation_fee").length), detail: "quotation accepted/payment due", icon: CreditCard },
    { label: "Pending proofs", value: String((payments ?? []).filter((item) => item.payment_status === "pending").length), detail: "need staff verification", icon: CreditCard },
    { label: "Upcoming events", value: String(upcoming.length), detail: `${eventsThisWeek.length} this week`, icon: CalendarDays },
    { label: "Unpaid balances", value: String((reservations ?? []).filter((item) => item.final_payment_status !== "paid").length), detail: "final payment open", icon: CreditCard },
    { label: "Avg feedback", value: avgFeedback ? avgFeedback.toFixed(1) : "0.0", detail: `${feedback?.length ?? 0} responses`, icon: Star },
    { label: "Cancellations", value: String((reservations ?? []).filter((item) => item.status === "cancelled").length), detail: "archived/cancelled records", icon: Users },
    { label: "Pipeline value", value: money((reservations ?? []).reduce((sum, item) => sum + Number(item.total_amount ?? 0), 0)), detail: "reservation totals", icon: CreditCard },
  ];

  return (
    <div className="space-y-6">
      <section className="rounded-md border border-neutral-200 bg-white p-6">
        <p className="text-xs font-medium uppercase tracking-[0.18em] text-neutral-500">Dashboard</p>
        <div className="mt-3 flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
          <div>
            <h1 className="text-3xl font-semibold tracking-tight text-neutral-950">Catering command center</h1>
            <p className="mt-2 max-w-2xl text-sm leading-6 text-neutral-600">
              Start with new inquiries, prepare quotations, verify reservation fees, and keep upcoming events ready for service.
            </p>
          </div>
          <Link href="/admin/inquiries" className="h-10 rounded-md bg-neutral-950 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-800">
            Review inquiries
          </Link>
        </div>
      </section>

      <section className="grid gap-4 lg:grid-cols-3">
        <ActionCard href="/admin/inquiries" icon={Inbox} title="Review new inquiries" detail="Check customer details, event date, pax, package, and notes." />
        <ActionCard href="/admin/quotations" icon={FileText} title="Prepare quotations" detail="Turn reviewed inquiries into quotation records for customer approval." />
        <ActionCard href="/admin/payments" icon={CreditCard} title="Verify payments" detail="Confirm reservation fees before marking bookings as reserved." />
      </section>

      <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        {stats.map((stat) => {
          const Icon = stat.icon;
          return (
            <div key={stat.label} className="rounded-md border border-neutral-200 bg-white p-5">
              <div className="flex items-center justify-between">
                <p className="text-sm text-neutral-500">{stat.label}</p>
                <Icon className="h-4 w-4 text-neutral-400" />
              </div>
              <p className="mt-3 text-2xl font-semibold text-neutral-950">{stat.value}</p>
              <p className="mt-1 text-sm text-neutral-500">{stat.detail}</p>
            </div>
          );
        })}
      </section>

      <section className="grid gap-6 xl:grid-cols-3">
        <Panel title="Newest inquiries">
          {(inquiries ?? []).slice(0, 6).map((item) => (
            <Row key={item.id} primary={item.customer_name} secondary={`${item.reference_code || item.ref_code} - ${item.event_type}`} meta={item.status} />
          ))}
        </Panel>
        <Panel title="Upcoming events">
          {upcoming.slice(0, 6).map((item) => (
            <Row key={item.id} primary={item.customer_name} secondary={`${item.reservation_code} - ${item.event_date}`} meta={item.status} />
          ))}
        </Panel>
        <Panel title="Open tasks">
          {(tasks ?? []).filter((item) => item.status !== "completed").slice(0, 6).map((item, index) => (
            <Row key={`${item.due_at}-${index}`} primary={item.status.replaceAll("_", " ")} secondary={item.due_at ?? "No due date"} meta="task" />
          ))}
        </Panel>
      </section>
    </div>
  );
}

function ActionCard({ href, icon: Icon, title, detail }: { href: string; icon: React.ElementType; title: string; detail: string }) {
  return (
    <Link href={href} className="rounded-md border border-neutral-200 bg-white p-5 transition hover:border-neutral-400 hover:shadow-sm">
      <div className="flex items-center gap-3">
        <span className="flex size-10 items-center justify-center rounded-md bg-neutral-950 text-white">
          <Icon className="h-4 w-4" />
        </span>
        <p className="font-semibold text-neutral-950">{title}</p>
      </div>
      <p className="mt-3 text-sm leading-6 text-neutral-600">{detail}</p>
    </Link>
  );
}

function Panel({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div className="rounded-md border border-neutral-200 bg-white p-5">
      <h2 className="text-lg font-semibold text-neutral-950">{title}</h2>
      <div className="mt-5 space-y-3">{children}</div>
    </div>
  );
}

function Row({ primary, secondary, meta }: { primary: string; secondary: string; meta: string }) {
  return (
    <div className="flex items-center justify-between rounded-md border border-neutral-200 p-3 text-sm">
      <div>
        <p className="font-medium capitalize text-neutral-950">{primary}</p>
        <p className="mt-1 text-neutral-500">{secondary}</p>
      </div>
      <span className="rounded-md bg-neutral-100 px-2 py-1 text-xs uppercase tracking-[0.12em] text-neutral-600">
        {meta.replaceAll("_", " ")}
      </span>
    </div>
  );
}
