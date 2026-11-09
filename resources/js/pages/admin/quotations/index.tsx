import { Link, router } from "@inertiajs/react";
import { formatPesos } from "../../../features/packages/money";
import AdminLayout from "../../../layouts/admin-layout";
import { formatManilaEvent } from "../../../lib/date-time";

type Quote = {
    id: number;
    reference: string;
    origin: "reservation_request" | "manual";
    status: string;
    customer_name: string;
    event_date: string | null;
    event_start_time: string | null;
    reservation_request: { id: number; reference: string } | null;
    current_version: {
        version_number: number;
        total_minor: string;
        status: string;
        valid_until: string | null;
    } | null;
};

export default function QuotationsIndex({
    quotations,
    status,
}: {
    quotations: { data: Quote[] };
    status: string;
}) {
    return (
        <AdminLayout
            title="Quotations"
            description="Commercial drafts and issued quotations. Issuance does not confirm a reservation or verify payment."
            section="quotations"
        >
            <div className="flex flex-wrap items-end justify-between gap-3">
                <label className="block w-52 text-sm font-medium">
                    Status
                    <select
                        value={status}
                        onChange={(event) =>
                            router.get(
                                "/admin/quotations",
                                event.target.value
                                    ? { status: event.target.value }
                                    : {},
                            )
                        }
                        className="mt-1 w-full"
                    >
                        <option value="">All statuses</option>
                        {[
                            "draft",
                            "issued",
                            "accepted",
                            "withdrawn",
                            "expired",
                        ].map((item) => (
                            <option key={item} value={item}>
                                {item}
                            </option>
                        ))}
                    </select>
                </label>
                <div className="flex gap-2">
                    <Link
                        href="/admin/quotations/create"
                        className="rounded-lg bg-emerald-800 px-4 py-2 text-sm font-semibold text-white"
                    >
                        New manual quotation
                    </Link>
                    <Link
                        href="/admin/business-policies"
                        className="rounded-lg border border-emerald-800 px-4 py-2 text-sm font-semibold text-emerald-900"
                    >
                        Business Policies
                    </Link>
                </div>
            </div>
            <div className="mt-6 overflow-hidden rounded-2xl border bg-white">
                <table className="min-w-full text-left text-sm">
                    <thead className="bg-slate-50 text-slate-600">
                        <tr>
                            <th className="p-4">Reference</th>
                            <th className="p-4">Customer / event</th>
                            <th className="p-4">Current version</th>
                            <th className="p-4">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {quotations.data.map((quote) => (
                            <tr key={quote.id} className="border-t">
                                <td className="p-4">
                                    <Link
                                        href={`/admin/quotations/${quote.id}`}
                                        className="font-semibold text-emerald-800 underline"
                                    >
                                        {quote.reference}
                                    </Link>
                                    <span className="block text-slate-500">
                                        {quote.reservation_request
                                            ? `Request ${quote.reservation_request.reference}`
                                            : "Manual quotation"}
                                    </span>
                                </td>
                                <td className="p-4">
                                    {quote.customer_name}
                                    <span className="block text-slate-500">
                                        {formatManilaEvent(
                                            quote.event_date,
                                            quote.event_start_time,
                                        )}
                                    </span>
                                </td>
                                <td className="p-4">
                                    {quote.current_version
                                        ? `v${quote.current_version.version_number} · ${formatPesos(quote.current_version.total_minor)}`
                                        : "—"}
                                </td>
                                <td className="p-4">
                                    {quote.status.replaceAll("_", " ")}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {quotations.data.length === 0 && (
                    <p className="p-6 text-sm text-slate-600">
                        No quotations match this filter. Start from a
                        reservation request when a customer needs a commercial
                        quote.
                    </p>
                )}
            </div>
        </AdminLayout>
    );
}
