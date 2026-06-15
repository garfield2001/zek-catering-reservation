import Link from "next/link";
import { createClient } from "@/lib/server";

type InquiryRow = {
  id: string;
  ref_code: string | null;
  reference_code: string | null;
  customer_name: string;
  customer_phone: string | null;
  event_type: string;
  event_date: string;
  guest_count: number | null;
  status: string;
  created_at: string;
  catering_packages: { name: string } | { name: string }[] | null;
};

export default async function AdminInquiriesPage() {
  const supabase = await createClient();
  const { data: inquiries } = await supabase
    .from("inquiries")
    .select("id, ref_code, reference_code, customer_name, customer_phone, event_type, event_date, guest_count, status, created_at, catering_packages(name)")
    .order("created_at", { ascending: false });

  const rows = (inquiries ?? []) as InquiryRow[];
  const active = rows.filter((item) => !["converted", "confirmed", "cancelled", "closed_lost", "declined"].includes(item.status));

  return (
    <div className="space-y-6">
      <section className="rounded-md border border-neutral-200 bg-white p-6">
        <p className="text-xs font-medium uppercase tracking-[0.18em] text-neutral-500">Inquiries</p>
        <div className="mt-3 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
          <div>
            <h1 className="text-3xl font-semibold tracking-tight text-neutral-950">Customer inquiry pipeline</h1>
            <p className="mt-2 max-w-2xl text-sm leading-6 text-neutral-600">
              New customer requests appear here first. Open an inquiry, review event details, then prepare the quotation or follow up with the customer.
            </p>
          </div>
          <Link href="/admin/inquiries/new" className="inline-flex h-10 items-center rounded-md bg-neutral-950 px-4 text-sm font-medium text-white">
            Manual inquiry
          </Link>
        </div>
      </section>

      <section className="grid gap-4 lg:grid-cols-[1.1fr_0.9fr]">
        <div className="rounded-md border border-amber-200 bg-amber-50 p-5">
          <p className="text-sm font-semibold text-amber-950">Staff workflow</p>
          <div className="mt-3 grid gap-3 text-sm text-amber-950 sm:grid-cols-3">
            <Step number="1" title="Open new lead" detail="Confirm event date, pax, venue, and contact details." />
            <Step number="2" title="Build quotation" detail="Check selected package, menu slots, add-ons, and requirements." />
            <Step number="3" title="Collect fee" detail="Move to reservation only after verified reservation fee payment." />
          </div>
        </div>
        <div className="grid gap-3 sm:grid-cols-2">
          <Metric label="Total inquiries" value={String(rows.length)} />
          <Metric label="Needs review" value={String(rows.filter((item) => ["new", "new_inquiry"].includes(item.status)).length)} />
          <Metric label="Active" value={String(active.length)} />
          <Metric label="Awaiting fee" value={String(rows.filter((item) => item.status === "awaiting_reservation_fee").length)} />
        </div>
      </section>

      <section className="overflow-hidden rounded-md border border-neutral-200 bg-white">
        <div className="flex flex-col justify-between gap-3 border-b border-neutral-200 p-4 sm:flex-row sm:items-center">
          <div>
            <h2 className="text-lg font-semibold text-neutral-950">All inquiries</h2>
            <p className="mt-1 text-sm text-neutral-500">Newest inquiries are listed first. The action column tells staff what to do next.</p>
          </div>
        </div>
        {rows.length === 0 ? (
          <div className="p-8 text-center">
            <p className="font-semibold text-neutral-950">No inquiries yet</p>
            <p className="mt-2 text-sm text-neutral-500">Website inquiries and manual staff entries will appear here.</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-neutral-200 text-sm">
              <thead className="bg-neutral-50 text-left text-xs uppercase tracking-[0.12em] text-neutral-500">
                <tr>
                  {["Ref", "Customer", "Event", "Date", "Pax", "Package", "Status", "Next action"].map((column) => (
                    <th key={column} className="px-4 py-3 font-medium">{column}</th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-200">
                {rows.map((item) => {
                  const packageInfo = Array.isArray(item.catering_packages) ? item.catering_packages[0] : item.catering_packages;
                  const status = statusMeta(item.status);

                  return (
                    <tr key={item.id} className="hover:bg-neutral-50">
                      <td className="whitespace-nowrap px-4 py-4 font-medium text-neutral-950">{item.reference_code || item.ref_code}</td>
                      <td className="whitespace-nowrap px-4 py-4 text-neutral-700">
                        {item.customer_name}
                        <span className="block text-xs text-neutral-500">{item.customer_phone ?? "No phone"}</span>
                      </td>
                      <td className="whitespace-nowrap px-4 py-4 text-neutral-700">{item.event_type}</td>
                      <td className="whitespace-nowrap px-4 py-4 text-neutral-700">{item.event_date}</td>
                      <td className="whitespace-nowrap px-4 py-4 text-neutral-700">{item.guest_count ?? "TBD"}</td>
                      <td className="whitespace-nowrap px-4 py-4 text-neutral-700">{packageInfo?.name ?? "TBD"}</td>
                      <td className="whitespace-nowrap px-4 py-4"><Status value={item.status} /></td>
                      <td className="whitespace-nowrap px-4 py-4">
                        <Link href={`/admin/inquiries/${item.id}`} className="inline-flex h-9 items-center rounded-md border border-neutral-300 px-3 text-sm font-medium text-neutral-950 hover:bg-neutral-100">
                          {status.action}
                        </Link>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
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

function Step({ number, title, detail }: { number: string; title: string; detail: string }) {
  return (
    <div className="rounded-md border border-amber-200 bg-white/70 p-3">
      <p className="text-xs font-semibold uppercase tracking-[0.12em] text-amber-700">Step {number}</p>
      <p className="mt-2 font-semibold text-neutral-950">{title}</p>
      <p className="mt-1 text-xs leading-5 text-neutral-600">{detail}</p>
    </div>
  );
}

function Status({ value }: { value: string }) {
  const status = statusMeta(value);
  return <span className={status.className}>{status.label}</span>;
}

function statusMeta(value: string) {
  const label = value.replaceAll("_", " ");

  if (["new", "new_inquiry"].includes(value)) {
    return {
      label,
      action: "Review details",
      className: "rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-xs capitalize text-blue-700",
    };
  }

  if (["quoted", "quotation_sent"].includes(value)) {
    return {
      label,
      action: "Check quotation",
      className: "rounded-full border border-violet-200 bg-violet-50 px-2.5 py-1 text-xs capitalize text-violet-700",
    };
  }

  if (value === "awaiting_reservation_fee") {
    return {
      label,
      action: "Verify fee",
      className: "rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs capitalize text-amber-700",
    };
  }

  if (["converted", "confirmed"].includes(value)) {
    return {
      label,
      action: "View record",
      className: "rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs capitalize text-emerald-700",
    };
  }

  if (["cancelled", "closed_lost", "declined"].includes(value)) {
    return {
      label,
      action: "View archive",
      className: "rounded-full border border-neutral-200 bg-neutral-100 px-2.5 py-1 text-xs capitalize text-neutral-600",
    };
  }

  return {
    label,
    action: "Open details",
    className: "rounded-full border border-neutral-300 px-2.5 py-1 text-xs capitalize text-neutral-700",
  };
}
