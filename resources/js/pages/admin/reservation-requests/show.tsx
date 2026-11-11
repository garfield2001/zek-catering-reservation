import { Link, router, usePage } from "@inertiajs/react";
import { useState } from "react";
import { ConfirmDialog } from "../../../components/ui/confirm-dialog";
import { formatPesos } from "../../../features/packages/money";
import { CaseWorkflow } from "../../../features/reservations/components/case-workflow";
import {
    formatManilaEvent,
    formatManilaTime,
    formatManilaTimestamp,
} from "../../../lib/date-time";
import AdminLayout from "../../../layouts/admin-layout";

type RequestLine = {
    id: number;
    line_type: "main_package" | "addon_package" | "listing";
    quantity: number;
    estimated_total_minor: string;
};

type StatusEvent = {
    id: number;
    from_status: string | null;
    to_status: string;
    created_at: string;
    actor: { name: string } | null;
};

type ReservationRequest = {
    id: number;
    reference: string;
    status: string;
    customer_name: string;
    customer_mobile: string;
    customer_email: string | null;
    event_date: string;
    event_start_time: string;
    event_end_time: string;
    timezone: string;
    venue_information: string;
    event_notes: string | null;
    estimated_subtotal_minor: string;
    origin: "public" | "manual" | "pencil_booking";
    pencil_booking: { id: number } | null;
    lines: RequestLine[];
    status_events: StatusEvent[];
    quotation: {
        id: number;
        status: string;
        current_version: { version_number: number } | null;
    } | null;
};

type StatusOption = { value: string; label: string };

export default function ReservationRequestShow({
    request,
    canManage,
    canCreateQuotation,
    allowedStatuses,
}: {
    request: ReservationRequest;
    canManage: boolean;
    canCreateQuotation: boolean;
    allowedStatuses: StatusOption[];
}) {
    const accessCode = usePage<{ flash: { customer_access_code?: string } }>()
        .props.flash?.customer_access_code;
    const update = (status: string) =>
        router.put(`/admin/reservation-requests/${request.id}/status`, {
            status,
        });
    const quotationLink = request.quotation
        ? `/admin/quotations/${request.quotation.id}`
        : null;
    const quotationAccepted = request.quotation?.status === "accepted";
    const [confirmAccessCodeReset, setConfirmAccessCodeReset] = useState(false);

    return (
        <AdminLayout
            title={request.reference}
            description="Submitted inquiry only. It does not reserve an event date or confirm availability."
            section="requests"
        >
            <CaseWorkflow
                steps={[
                    {
                        label: "Inquiry",
                        detail: request.status.replaceAll("_", " "),
                        state:
                            request.status === "closed"
                                ? "complete"
                                : "current",
                    },
                    {
                        label: "Quotation",
                        detail: request.quotation
                            ? `${request.quotation.status} version ${request.quotation.current_version?.version_number ?? ""}`
                            : "Create a quotation when the inquiry is ready.",
                        state: !request.quotation
                            ? "upcoming"
                            : quotationAccepted
                              ? "complete"
                              : "current",
                        href: quotationLink ?? undefined,
                    },
                    {
                        label: "Payment",
                        detail: quotationAccepted
                            ? "Review submitted or verified payments in the quotation case."
                            : "Available after a quotation is accepted.",
                        state: quotationAccepted ? "current" : "upcoming",
                    },
                    {
                        label: "Reservation",
                        detail: quotationAccepted
                            ? "Capacity remains uncommitted until an Administrator confirms the event."
                            : "Confirmation requires acceptance, payment readiness, and capacity.",
                        state: "upcoming",
                    },
                ]}
            />
            <div className="grid gap-6 lg:grid-cols-2">
                <section className="rounded-2xl border bg-white p-5">
                    <h2 className="font-semibold">Customer and event</h2>
                    <p className="mt-2 text-sm text-slate-600">
                        Origin:{" "}
                        <strong>
                            {request.origin === "pencil_booking"
                                ? "Pencil booking conversion"
                                : request.origin === "manual"
                                  ? "Internal request"
                                  : "Customer website"}
                        </strong>
                        {request.pencil_booking && (
                            <>
                                {" "}
                                ·{" "}
                                <Link
                                    href={`/admin/pencil-bookings/${request.pencil_booking.id}`}
                                    className="font-semibold text-emerald-800 underline"
                                >
                                    View pencil booking
                                </Link>
                            </>
                        )}
                    </p>
                    <dl className="mt-4 space-y-2 text-sm">
                        <div>
                            <dt className="text-slate-500">Customer</dt>
                            <dd>{request.customer_name}</dd>
                        </div>
                        <div>
                            <dt className="text-slate-500">Contact</dt>
                            <dd>
                                {request.customer_mobile} ·{" "}
                                {request.customer_email}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-slate-500">Schedule</dt>
                            <dd>
                                {formatManilaEvent(
                                    request.event_date,
                                    request.event_start_time,
                                )}{" "}
                                – {formatManilaTime(request.event_end_time)} (
                                {request.timezone})
                            </dd>
                        </div>
                        <div>
                            <dt className="text-slate-500">Tentative venue</dt>
                            <dd>{request.venue_information}</dd>
                        </div>
                        {request.event_notes && (
                            <div>
                                <dt className="text-slate-500">Notes</dt>
                                <dd>{request.event_notes}</dd>
                            </div>
                        )}
                    </dl>
                </section>
                <section className="rounded-2xl border bg-white p-5">
                    <h2 className="font-semibold">Planning estimate</h2>
                    <p className="mt-2 text-3xl font-semibold text-emerald-950">
                        {formatPesos(request.estimated_subtotal_minor)}
                    </p>
                    <p className="mt-2 text-sm text-slate-600">
                        Frozen from published commercial information at
                        submission. It is not a final quotation.
                    </p>
                    <ul className="mt-4 space-y-2 text-sm">
                        {request.lines.map((line) => (
                            <li
                                key={line.id}
                                className="rounded-lg bg-slate-50 p-3"
                            >
                                {line.line_type.replaceAll("_", " ")} · Quantity{" "}
                                {line.quantity} ·{" "}
                                {formatPesos(line.estimated_total_minor)}
                            </li>
                        ))}
                    </ul>
                </section>
            </div>

            {canCreateQuotation && (
                <section className="mt-6 rounded-2xl border bg-white p-5">
                    <h2 className="font-semibold">Quotation</h2>
                    {request.quotation ? (
                        <>
                            <p className="mt-2 text-sm text-slate-600">
                                This request already has one quotation case.
                                Continue its version workflow instead of
                                creating another case.
                            </p>
                            <Link
                                href={quotationLink!}
                                className="mt-4 inline-block rounded-lg bg-emerald-800 px-4 py-2 text-sm font-semibold text-white"
                            >
                                {request.quotation.status === "draft"
                                    ? "Continue quotation draft"
                                    : "View quotation"}
                            </Link>
                        </>
                    ) : (
                        <>
                            <p className="mt-2 text-sm text-slate-600">
                                Create a commercial draft from this request’s
                                frozen selection and estimate. This does not
                                confirm the event.
                            </p>
                            <Link
                                href={`/admin/reservation-requests/${request.id}/prepare-quotation`}
                                className="mt-4 inline-block rounded-lg bg-emerald-800 px-4 py-2 text-sm font-semibold text-white"
                            >
                                Prepare quotation
                            </Link>
                        </>
                    )}
                </section>
            )}

            {canManage && (
                <section className="mt-6 rounded-2xl border bg-white p-5">
                    <h2 className="font-semibold">Customer access code</h2>
                    {accessCode ? (
                        <div className="mt-3 rounded-xl border border-amber-300 bg-amber-50 p-4">
                            <p className="font-medium">
                                New access code: <code>{accessCode}</code>
                            </p>
                            <p className="mt-1 text-sm text-amber-950">
                                Communicate it privately now. It will not be
                                shown again.
                            </p>
                        </div>
                    ) : (
                        <p className="mt-2 text-sm text-slate-600">
                            Reset only when the customer cannot use the original
                            code. The reset invalidates the prior code and is
                            recorded in Activity History.
                        </p>
                    )}
                    <button
                        type="button"
                        onClick={() => setConfirmAccessCodeReset(true)}
                        className="mt-4 rounded-lg border border-emerald-800 px-4 py-2 text-sm font-semibold text-emerald-900"
                    >
                        Generate replacement code
                    </button>
                </section>
            )}
            <ConfirmDialog
                open={confirmAccessCodeReset}
                onClose={() => setConfirmAccessCodeReset(false)}
                title="Generate a replacement customer access code?"
                description="The prior code will stop working immediately. The replacement is shown only once to this authorized browser, so communicate it privately."
                confirmLabel="Generate replacement code"
                onConfirm={() =>
                    router.post(
                        `/admin/reservation-requests/${request.id}/customer-access-code`,
                        {},
                        { onFinish: () => setConfirmAccessCodeReset(false) },
                    )
                }
            />

            <section className="mt-6 rounded-2xl border bg-white p-5">
                <h2 className="font-semibold">Request status</h2>
                <p className="mt-2 text-sm">
                    Current:{" "}
                    <strong>{request.status.replaceAll("_", " ")}</strong>
                </p>
                {canManage && allowedStatuses.length > 0 ? (
                    <div className="mt-4 flex flex-wrap gap-2">
                        {allowedStatuses.map((status) => (
                            <button
                                key={status.value}
                                onClick={() => update(status.value)}
                                className="rounded-lg border border-emerald-800 px-3 py-2 text-sm font-semibold text-emerald-900"
                            >
                                Mark {status.label}
                            </button>
                        ))}
                    </div>
                ) : (
                    <p className="mt-3 text-sm text-slate-600">
                        {allowedStatuses.length
                            ? "Your account can view this request but cannot change its status."
                            : "Closed requests have no further status transition."}
                    </p>
                )}
                <ol className="mt-5 space-y-3 text-sm">
                    {request.status_events.map((event) => (
                        <li
                            key={event.id}
                            className="rounded-lg bg-slate-50 p-3"
                        >
                            <span className="block text-slate-600">
                                {formatManilaTimestamp(event.created_at)}
                            </span>
                            <span>
                                {event.from_status ?? "New"} → {event.to_status}
                                {event.actor ? ` · ${event.actor.name}` : ""}
                            </span>
                        </li>
                    ))}
                </ol>
            </section>
        </AdminLayout>
    );
}
