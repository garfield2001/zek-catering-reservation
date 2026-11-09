import { Head, Link } from "@inertiajs/react";
import {
    formatManilaEvent,
    formatManilaTimestamp,
} from "../../../lib/date-time";
import AdminLayout from "../../../layouts/admin-layout";
import { formatPesos } from "../../../features/packages/money";

type Cancellation = {
    id: number;
    kind: "withdrawal" | "cancellation";
    status: string;
    requested_at: string;
    quotation: {
        id: number;
        reference: string;
        customer_name: string;
        event_date: string;
    };
    reservation: { reference: string; status: string } | null;
    requester: { name: string } | null;
    reviewer: { name: string } | null;
    refunds: Array<{ amount_minor: string; status: string }>;
};

export default function CancellationIndex({
    cancellations,
}: {
    cancellations: { data: Cancellation[] };
}) {
    return (
        <AdminLayout
            title="Cancellations & refunds"
            description="Review customer requests, finalized cancellations, and manual refund progress. A completed refund is recorded only after money was returned outside the application."
            section="reservations"
        >
            <Head title="Cancellations & refunds" />
            <div className="overflow-hidden rounded-2xl border bg-white">
                {cancellations.data.length === 0 ? (
                    <p className="p-6 text-sm text-slate-600">
                        No cancellation or refund cases require review.
                    </p>
                ) : (
                    <ul className="divide-y divide-slate-200">
                        {cancellations.data.map((cancellation) => {
                            const completed = cancellation.refunds
                                .filter(
                                    (refund) => refund.status === "completed",
                                )
                                .reduce(
                                    (total, refund) =>
                                        total + Number(refund.amount_minor),
                                    0,
                                );
                            return (
                                <li key={cancellation.id} className="p-5">
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <Link
                                                href={`/admin/reservations/quotations/${cancellation.quotation.id}`}
                                                className="font-semibold text-emerald-900 hover:underline"
                                            >
                                                {
                                                    cancellation.quotation
                                                        .reference
                                                }{" "}
                                                ·{" "}
                                                {
                                                    cancellation.quotation
                                                        .customer_name
                                                }
                                            </Link>
                                            <p className="mt-1 text-sm text-slate-600">
                                                {cancellation.kind ===
                                                "withdrawal"
                                                    ? "Withdrawal"
                                                    : "Cancellation"}{" "}
                                                requested{" "}
                                                {formatManilaTimestamp(
                                                    cancellation.requested_at,
                                                )}
                                                {" · "}
                                                {formatManilaEvent(
                                                    cancellation.quotation
                                                        .event_date,
                                                )}
                                            </p>
                                        </div>
                                        <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-700">
                                            {cancellation.status.replaceAll(
                                                "_",
                                                " ",
                                            )}
                                        </span>
                                    </div>
                                    <p className="mt-3 text-sm text-slate-700">
                                        {cancellation.refunds.length === 0
                                            ? "No refund is recorded."
                                            : `${cancellation.refunds.length} refund record${cancellation.refunds.length === 1 ? "" : "s"}; ${formatPesos(String(completed))} completed.`}
                                    </p>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>
        </AdminLayout>
    );
}
