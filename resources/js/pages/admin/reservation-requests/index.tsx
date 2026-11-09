import { Link, router } from "@inertiajs/react";
import { formatManilaEvent } from "../../../lib/date-time";
import AdminLayout from "../../../layouts/admin-layout";

type RequestSummary = {
    id: number;
    reference: string;
    status: string;
    status_label: string;
    customer_name: string;
    event_date: string;
    event_start_time: string;
    guest_count: number;
    quotation: {
        id: number;
        status: string;
        version_number: number | null;
    } | null;
};

type StatusOption = { value: string; label: string };

export default function ReservationRequestsIndex({
    requests,
    status,
    statuses,
}: {
    requests: { data: RequestSummary[] };
    status: string;
    statuses: StatusOption[];
}) {
    return (
        <AdminLayout
            title="Reservation Requests"
            description="Inquiry-only customer requests awaiting review. A request does not reserve an event date."
            section="requests"
        >
            <label className="block max-w-xs text-sm font-medium">
                Status
                <select
                    value={status}
                    onChange={(event) =>
                        router.get(
                            "/admin/reservation-requests",
                            event.target.value
                                ? { status: event.target.value }
                                : {},
                            { preserveState: true, replace: true },
                        )
                    }
                    className="mt-1 w-full"
                >
                    <option value="">All statuses</option>
                    {statuses.map((item) => (
                        <option key={item.value} value={item.value}>
                            {item.label}
                        </option>
                    ))}
                </select>
            </label>

            <div className="mt-6 overflow-x-auto rounded-2xl border bg-white">
                <table className="min-w-[760px] w-full text-left text-sm">
                    <thead className="bg-slate-50 text-slate-600">
                        <tr>
                            <th className="p-4">Request</th>
                            <th className="p-4">Customer and event</th>
                            <th className="p-4">Quotation progress</th>
                            <th className="p-4">Next action</th>
                        </tr>
                    </thead>
                    <tbody>
                        {requests.data.map((item) => (
                            <tr key={item.id} className="border-t align-top">
                                <td className="p-4">
                                    <Link
                                        className="font-semibold text-emerald-800 underline"
                                        href={`/admin/reservation-requests/${item.id}`}
                                    >
                                        {item.reference}
                                    </Link>
                                    <span className="mt-1 block text-slate-500">
                                        {item.status_label}
                                    </span>
                                </td>
                                <td className="p-4">
                                    {item.customer_name}
                                    <span className="block text-slate-500">
                                        {formatManilaEvent(
                                            item.event_date,
                                            item.event_start_time,
                                        )}{" "}
                                        · {item.guest_count} guests
                                    </span>
                                </td>
                                <td className="p-4">
                                    {item.quotation
                                        ? `v${item.quotation.version_number ?? 1} · ${item.quotation.status.replaceAll("_", " ")}`
                                        : "No quotation case"}
                                </td>
                                <td className="p-4">
                                    <Link
                                        href={
                                            item.quotation
                                                ? `/admin/quotations/${item.quotation.id}`
                                                : `/admin/reservation-requests/${item.id}`
                                        }
                                        className="font-semibold text-emerald-800 underline"
                                    >
                                        {item.quotation
                                            ? item.quotation.status === "draft"
                                                ? "Continue Draft"
                                                : "View Quotation"
                                            : "Prepare Quotation"}
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {requests.data.length === 0 && (
                    <p className="p-6 text-sm text-slate-600">
                        No requests match this filter.
                    </p>
                )}
            </div>
        </AdminLayout>
    );
}
