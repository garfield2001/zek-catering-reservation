import Link from "next/link";
import type { ReactNode } from "react";
import { createClient } from "@/lib/server";

export default async function AdminReservationsPage({
  searchParams,
}: {
  searchParams?: Promise<{ created?: string }>;
}) {
  const params = await searchParams;
  const supabase = await createClient();
  const [{ data: inquiries }, { data: reservations }, { data: notifications }] = await Promise.all([
    supabase
      .from("inquiries")
      .select("id, ref_code, customer_name, customer_email, customer_phone, event_type, event_date, guest_count, status, created_at")
      .order("created_at", { ascending: false }),
    supabase
      .from("reservations")
      .select("reservation_code, customer_name, customer_email, event_type, event_date, guest_count, total_amount, deposit_required, deposit_paid, status")
      .order("event_date", { ascending: true }),
    supabase
      .from("notification_logs")
      .select("channel, recipient, subject, status, provider_reference, created_at")
      .order("created_at", { ascending: false })
      .limit(8),
  ]);

  const openInquiries = (inquiries ?? []).filter((item) => !["converted", "cancelled", "closed_lost"].includes(item.status));

  return (
    <div className="space-y-6">
      <section className="rounded-md border border-neutral-200 bg-white p-6">
        <p className="text-xs font-medium uppercase tracking-[0.18em] text-neutral-500">Reservations</p>
        <h1 className="mt-3 text-3xl font-semibold tracking-tight text-neutral-950">Inquiry to booking workflow</h1>
        <p className="mt-2 max-w-2xl text-sm leading-6 text-neutral-600">
          Customers submit inquiries. Admin or staff contact them, quote the event, create the final reservation, then email the customer summary.
        </p>
        <div className="mt-5 flex flex-wrap gap-3">
          <Link href="/admin/reservations/new" className="inline-flex h-10 items-center rounded-md bg-neutral-950 px-4 text-sm font-medium text-white hover:bg-neutral-800">
            Create reservation
          </Link>
          <Link href="/reserve" className="inline-flex h-10 items-center rounded-md border border-neutral-300 px-4 text-sm font-medium text-neutral-950 hover:bg-neutral-100">
            Open customer form
          </Link>
        </div>
        {params?.created && (
          <p className="mt-4 rounded-md bg-neutral-100 p-3 text-sm text-neutral-700">
            Reservation {params.created} was created. If email was checked, a development email log was recorded below.
          </p>
        )}
      </section>

      <section className="grid gap-4 md:grid-cols-4">
        <Metric label="Open inquiries" value={String(openInquiries.length)} />
        <Metric label="Final reservations" value={String((reservations ?? []).length)} />
        <Metric label="Confirmed" value={String((reservations ?? []).filter((item) => item.status === "confirmed").length)} />
        <Metric
          label="Pipeline value"
          value={`PHP ${(reservations ?? []).reduce((sum, item) => sum + Number(item.total_amount ?? 0), 0).toLocaleString()}`}
        />
      </section>

      <DataTable
        title="Customer inquiries"
        columns={["Ref", "Customer", "Event", "Date", "Guests", "Status", "Next step"]}
        rows={(inquiries ?? []).map((item) => [
          item.ref_code,
          <span key="customer">
            {item.customer_name}
            <span className="block text-xs text-neutral-500">{item.customer_email}</span>
          </span>,
          item.event_type,
          item.event_date ?? "To confirm",
          String(item.guest_count ?? "TBD"),
          <StatusBadge key="status" value={item.status} />,
          item.status === "converted" ? (
            <span key="done" className="text-neutral-500">
              Converted
            </span>
          ) : (
            <Link key="convert" href={`/admin/reservations/new?inquiry=${item.ref_code}`} className="font-medium text-neutral-950 underline underline-offset-4">
              Create reservation
            </Link>
          ),
        ])}
      />

      <DataTable
        title="Finalized reservations"
        columns={["Code", "Customer", "Event", "Date", "Guests", "Amount", "Deposit", "Status"]}
        rows={(reservations ?? []).map((item) => [
          item.reservation_code,
          <span key="customer">
            {item.customer_name}
            <span className="block text-xs text-neutral-500">{item.customer_email}</span>
          </span>,
          item.event_type,
          item.event_date,
          String(item.guest_count),
          `PHP ${Number(item.total_amount).toLocaleString()}`,
          `PHP ${Number(item.deposit_paid ?? 0).toLocaleString()} / ${Number(item.deposit_required ?? 0).toLocaleString()}`,
          <StatusBadge key="status" value={item.status} />,
        ])}
      />

      <DataTable
        title="Development notification log"
        columns={["Channel", "Recipient", "Subject", "Status", "Provider"]}
        rows={(notifications ?? []).map((item) => [
          item.channel,
          item.recipient,
          item.subject ?? "No subject",
          <StatusBadge key="status" value={item.status} />,
          item.provider_reference ?? "local",
        ])}
      />
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

function StatusBadge({ value }: { value: string }) {
  return (
    <span className="inline-flex rounded-full border border-neutral-300 px-2.5 py-1 text-xs font-medium capitalize text-neutral-700">
      {value.replaceAll("_", " ")}
    </span>
  );
}

function DataTable({ title, columns, rows }: { title: string; columns: string[]; rows: ReactNode[][] }) {
  return (
    <section className="overflow-hidden rounded-md border border-neutral-200 bg-white">
      <div className="border-b border-neutral-200 p-4">
        <h2 className="text-lg font-semibold text-neutral-950">{title}</h2>
      </div>
      <div className="overflow-x-auto">
        <table className="min-w-full divide-y divide-neutral-200 text-sm">
          <thead className="bg-neutral-50 text-left text-xs uppercase tracking-[0.12em] text-neutral-500">
            <tr>
              {columns.map((column) => (
                <th key={column} className="px-4 py-3 font-medium">
                  {column}
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-200">
            {rows.length === 0 ? (
              <tr>
                <td colSpan={columns.length} className="px-4 py-8 text-center text-neutral-500">
                  No records yet.
                </td>
              </tr>
            ) : (
              rows.map((row, rowIndex) => (
                <tr key={rowIndex} className="hover:bg-neutral-50">
                  {row.map((cell, cellIndex) => (
                    <td key={`${rowIndex}-${cellIndex}`} className="whitespace-nowrap px-4 py-4 text-neutral-700">
                      {cell}
                    </td>
                  ))}
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </section>
  );
}
