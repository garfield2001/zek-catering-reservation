import { Link, router } from "@inertiajs/react";
import { formatPesos } from "../../../features/packages/money";
import { formatManilaEvent } from "../../../lib/date-time";
import AdminLayout from "../../../layouts/admin-layout";

type Request = {
    id: number;
    reference: string;
    customer_name: string;
    customer_mobile: string;
    customer_email: string;
    event_date: string;
    event_start_time: string;
    venue_information: string;
    event_notes: string | null;
    estimated_subtotal_minor: string;
};

type Draft = {
    lines: Array<{
        line_number: number;
        description: string;
        quantity: number;
        total_minor: string;
    }>;
    subtotal_minor: number;
};

export default function PrepareQuotation({
    request,
    draft,
}: {
    request: Request;
    draft: Draft;
}) {
    return (
        <AdminLayout
            title="Prepare Quotation"
            description="Review the customer’s frozen inquiry before saving a commercial Draft. Opening this page does not create a quotation."
            section="quotations"
        >
            <div className="max-w-3xl space-y-6">
                <section className="rounded-2xl border bg-white p-5">
                    <h2 className="font-semibold">1. Customer and event</h2>
                    <p className="mt-3 text-sm">
                        {request.customer_name} · {request.customer_mobile} ·{" "}
                        {request.customer_email}
                    </p>
                    <p className="mt-1 text-sm text-slate-600">
                        {formatManilaEvent(
                            request.event_date,
                            request.event_start_time,
                        )}{" "}
                        · {request.venue_information}
                    </p>
                    {request.event_notes && (
                        <p className="mt-2 text-sm text-slate-600">
                            Notes: {request.event_notes}
                        </p>
                    )}
                </section>
                <section className="rounded-2xl border bg-white p-5">
                    <h2 className="font-semibold">2. Package and selections</h2>
                    <ul className="mt-3 space-y-2 text-sm">
                        {draft.lines.map((line) => (
                            <li
                                key={line.line_number}
                                className="rounded-lg bg-slate-50 p-3"
                            >
                                {line.description} · Quantity {line.quantity} ·{" "}
                                {formatPesos(line.total_minor)}
                            </li>
                        ))}
                    </ul>
                </section>
                <section className="rounded-2xl border bg-white p-5">
                    <h2 className="font-semibold">3. Pricing</h2>
                    <p className="mt-2 text-2xl font-semibold">
                        {formatPesos(String(draft.subtotal_minor))}
                    </p>
                    <p className="mt-2 text-sm text-slate-600">
                        This starts from the immutable inquiry estimate. After
                        you save the Draft, authorized pricing adjustments are
                        recorded separately with their reasons.
                    </p>
                </section>
                <section className="rounded-2xl border bg-white p-5">
                    <h2 className="font-semibold">4. Business Policies</h2>
                    <p className="mt-2 text-sm text-slate-600">
                        The policy snapshot is applied only when you issue the
                        quotation. No policy approval screen is required.
                    </p>
                </section>
                <section className="flex flex-wrap justify-end gap-3">
                    <Link
                        href={`/admin/reservation-requests/${request.id}`}
                        className="rounded-lg border px-4 py-2 text-sm font-semibold"
                    >
                        Back to request
                    </Link>
                    <button
                        onClick={() =>
                            router.post(
                                `/admin/reservation-requests/${request.id}/quotations`,
                            )
                        }
                        className="rounded-lg bg-emerald-800 px-4 py-2 text-sm font-semibold text-white"
                    >
                        Save quotation draft
                    </button>
                </section>
            </div>
        </AdminLayout>
    );
}
