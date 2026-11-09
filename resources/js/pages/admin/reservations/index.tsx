import { Link } from "@inertiajs/react";
import { formatManilaEvent } from "../../../lib/date-time";
import AdminLayout from "../../../layouts/admin-layout";

type QueueRow = {
    id: number;
    reference: string;
    customer_name: string;
    event_date: string | null;
    event_start_time: string | null;
    latest_accepted_version: number | null;
    reservation: {
        reference: string;
        status: "confirmed" | "completed";
        confirmed_version_number: number;
    } | null;
    latest_decision: {
        outcome: "deferred" | "declined" | "confirmed";
        version_number: number;
    } | null;
    requires_reconfirmation: boolean;
};

export default function ReservationsIndex({
    quotations,
    canManageCapacity,
}: {
    quotations: {
        data: QueueRow[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    canManageCapacity: boolean;
}) {
    return (
        <AdminLayout
            title="Reservation confirmation"
            description="Review accepted quotations, verified reservation-payment readiness, and daily capacity before an Administrator confirms an event. Nothing in this queue holds capacity until confirmation."
            section="reservations"
        >
            <div className="mb-5 flex flex-wrap gap-3">
                {canManageCapacity && (
                    <Link
                        href="/admin/reservations/capacity"
                        className="rounded-lg border border-emerald-800 px-4 py-2 text-sm font-semibold text-emerald-900"
                    >
                        Manage date capacity
                    </Link>
                )}
                <Link
                    href="/admin/business-policies"
                    className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700"
                >
                    View Business Policies
                </Link>
            </div>
            <div className="overflow-hidden rounded-2xl border bg-white">
                <table className="w-full text-left text-sm">
                    <thead className="bg-slate-50 text-slate-600">
                        <tr>
                            <th className="p-4">Quotation</th>
                            <th className="p-4">Event</th>
                            <th className="p-4">Confirmation status</th>
                            <th className="p-4">
                                <span className="sr-only">Review</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {quotations.data.map((quotation) => (
                            <tr
                                key={quotation.id}
                                className="border-t align-top"
                            >
                                <td className="p-4">
                                    <strong className="block">
                                        {quotation.reference}
                                    </strong>
                                    <span className="text-slate-600">
                                        {quotation.customer_name}
                                    </span>
                                </td>
                                <td className="p-4 text-slate-700">
                                    {quotation.event_date
                                        ? formatManilaEvent(
                                              quotation.event_date,
                                              quotation.event_start_time,
                                          )
                                        : "Event date required before confirmation"}
                                </td>
                                <td className="p-4">
                                    <QueueStatus quotation={quotation} />
                                </td>
                                <td className="p-4 text-right">
                                    <Link
                                        href={`/admin/reservations/quotations/${quotation.id}`}
                                        className="font-semibold text-emerald-800 underline"
                                    >
                                        Review confirmation
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {quotations.data.length === 0 && (
                    <p className="p-8 text-center text-sm text-slate-600">
                        No accepted quotation cases are ready for reservation
                        review.
                    </p>
                )}
            </div>
            <nav aria-label="Pagination" className="mt-5 flex flex-wrap gap-2">
                {quotations.links.map((link, index) => (
                    <Link
                        key={`${link.label}-${index}`}
                        href={link.url ?? "#"}
                        preserveScroll
                        aria-current={link.active ? "page" : undefined}
                        className={`rounded border px-3 py-2 text-sm ${link.active ? "border-emerald-800 bg-emerald-800 text-white" : "border-slate-300 text-slate-700"} ${!link.url ? "pointer-events-none opacity-50" : ""}`}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ))}
            </nav>
        </AdminLayout>
    );
}

function QueueStatus({ quotation }: { quotation: QueueRow }) {
    if (quotation.reservation) {
        if (quotation.requires_reconfirmation) {
            return (
                <span className="font-medium text-amber-800">
                    Revision v{quotation.latest_accepted_version} requires
                    Administrator re-confirmation
                </span>
            );
        }
        return (
            <span className="font-medium text-emerald-800">
                {quotation.reservation.status} ·{" "}
                {quotation.reservation.reference}
            </span>
        );
    }
    if (quotation.latest_decision?.outcome === "deferred") {
        return (
            <span className="font-medium text-amber-800">
                Confirmation deferred
            </span>
        );
    }
    if (quotation.latest_decision?.outcome === "declined") {
        return (
            <span className="font-medium text-rose-800">
                Confirmation declined
            </span>
        );
    }
    return (
        <span className="font-medium text-slate-800">
            Awaiting Administrator review · accepted v
            {quotation.latest_accepted_version}
        </span>
    );
}
