import { Link, router } from "@inertiajs/react";
import type { ReactNode } from "react";
import { formatPesos } from "../../packages/money";
import {
    formatManilaEvent,
    formatManilaTimestamp,
} from "../../../lib/date-time";

export type QuotationVersion = {
    id: number;
    version_number: number;
    status: string;
    subtotal_minor: string;
    adjustment_minor: string;
    total_minor: string;
    valid_until: string | null;
    issued_at: string | null;
    line_snapshots: Array<{
        line_number: number;
        line_type: string;
        description?: string;
        quantity: number;
        total_minor: string;
    }>;
    terms_snapshot: {
        schema?: number;
        quotation_validity_days?: number;
        reservation_payment?: {
            type: string;
            amount_minor: string | null;
            basis_points: number | null;
            payment_deadline_hours: number;
        };
        final_payment_days_before_event?: number;
        menu_finalization?: { days_after_acceptance: number };
    } | null;
    adjustment_requests: Array<{
        id: number;
        status: string;
        requested_adjustment_minor: string;
        reason: string;
        requester: { name: string };
    }>;
    delivery_events: Array<{
        id: number;
        channel: string;
        destination_hint: string | null;
        note: string | null;
        created_at: string;
        recorder: { name: string };
    }>;
    acceptance: {
        method: string;
        accepted_by_name: string;
        accepted_at: string;
        payment_due_at: string;
        menu_finalization_due_at: string;
    } | null;
};

export type QuotationDetail = {
    id: number;
    reference: string;
    status: string;
    origin: string;
    customer_name: string;
    customer_mobile: string;
    customer_email: string | null;
    event_date: string | null;
    event_start_time: string | null;
    venue_information: string | null;
    reservation_request: { id: number; reference: string } | null;
    current_version: QuotationVersion;
    versions: QuotationVersion[];
};

export type QuotationPaymentBalance = {
    total_minor: number;
    verified_minor: number;
    remaining_minor: number;
    reservation_required_minor: number;
    reservation_remaining_minor: number;
    overpayment_minor: number;
    reservation_payment_overdue: boolean;
    final_payment_overdue: boolean;
};

export type QuotationPayment = {
    id: number;
    status: string;
    amount_minor: string | null;
    paid_at: string | null;
    transaction_reference: string | null;
    rejection_reason: string | null;
    reversal_reason: string | null;
    method: { name: string } | null;
    allocation_history: Array<{
        quotation_version_id: number;
        superseded_at: string | null;
        quotation_version: { version_number: number };
    }>;
    evidence_versions: Array<{
        id: number;
        version_number: number;
        submitted_at: string;
    }>;
    has_proof: boolean;
};

export function QuotationSummary({
    quotation,
    canManage,
    issueControl,
    withdrawControl,
}: {
    quotation: QuotationDetail;
    canManage: boolean;
    issueControl?: ReactNode;
    withdrawControl?: ReactNode;
}) {
    const current = quotation.current_version;

    return (
        <div className="grid gap-6 lg:grid-cols-2">
            <section className="rounded-2xl border bg-white p-5">
                <h2 className="font-semibold">Customer and event</h2>
                <dl className="mt-3 space-y-2 text-sm">
                    <OverviewDetail
                        label="Customer"
                        value={quotation.customer_name}
                    />
                    <OverviewDetail
                        label="Contact"
                        value={`${quotation.customer_mobile}${quotation.customer_email ? ` · ${quotation.customer_email}` : ""}`}
                    />
                    <OverviewDetail
                        label="Event"
                        value={formatManilaEvent(
                            quotation.event_date,
                            quotation.event_start_time,
                        )}
                    />
                    <OverviewDetail
                        label="Venue"
                        value={quotation.venue_information ?? "Not set"}
                    />
                    {quotation.reservation_request && (
                        <div>
                            <dt className="text-slate-500">Source request</dt>
                            <dd>
                                <Link
                                    href={`/admin/reservation-requests/${quotation.reservation_request.id}`}
                                    className="font-semibold text-emerald-800 underline"
                                >
                                    {quotation.reservation_request.reference}
                                </Link>
                            </dd>
                        </div>
                    )}
                </dl>
            </section>
            <section className="rounded-2xl border bg-white p-5">
                <h2 className="font-semibold">Current commercial version</h2>
                <p className="mt-2 text-3xl font-semibold text-emerald-950">
                    {formatPesos(current.total_minor)}
                </p>
                <p className="mt-1 text-sm text-slate-600">
                    v{current.version_number} · {current.status}
                </p>
                {issueControl}
                {withdrawControl}
                {canManage &&
                    current.status !== "draft" &&
                    quotation.status !== "accepted" && (
                        <button
                            onClick={() =>
                                router.post(
                                    `/admin/quotations/${quotation.id}/revisions`,
                                )
                            }
                            className="mt-4 rounded-lg border border-emerald-800 px-4 py-2 text-sm font-semibold text-emerald-900"
                        >
                            Create revised draft
                        </button>
                    )}
            </section>
        </div>
    );
}

export function QuotationVersionDetails({
    version,
}: {
    version: QuotationVersion;
}) {
    return (
        <section className="mt-6 grid gap-6 lg:grid-cols-2">
            <div className="rounded-2xl border bg-white p-5">
                <h2 className="font-semibold">Quoted selections</h2>
                <ul className="mt-3 space-y-2 text-sm">
                    {version.line_snapshots.map((line) => (
                        <li
                            key={line.line_number}
                            className="rounded-lg bg-slate-50 p-3"
                        >
                            {line.description ??
                                line.line_type.replaceAll("_", " ")}
                            {" · "}quantity {line.quantity}
                            {" · "}
                            {formatPesos(line.total_minor)}
                        </li>
                    ))}
                </ul>
                <p className="mt-3 text-sm">
                    Subtotal {formatPesos(version.subtotal_minor)} · Adjustment{" "}
                    {formatPesos(version.adjustment_minor)} ·{" "}
                    <strong>Total {formatPesos(version.total_minor)}</strong>
                </p>
            </div>
            <div className="rounded-2xl border bg-white p-5">
                <h2 className="font-semibold">Business Policy snapshot</h2>
                {version.terms_snapshot ? (
                    <div className="mt-3 space-y-3 text-sm">
                        <p>
                            Payment:{" "}
                            {version.terms_snapshot.reservation_payment
                                ?.type === "fixed_amount"
                                ? formatPesos(
                                      version.terms_snapshot.reservation_payment
                                          .amount_minor ?? "0",
                                  )
                                : `${(version.terms_snapshot.reservation_payment?.basis_points ?? 0) / 100}%`}{" "}
                            within{" "}
                            {
                                version.terms_snapshot.reservation_payment
                                    ?.payment_deadline_hours
                            }{" "}
                            hours after acceptance.
                        </p>
                        <p>
                            Menu finalization:{" "}
                            {
                                version.terms_snapshot.menu_finalization
                                    ?.days_after_acceptance
                            }{" "}
                            days after acceptance.
                        </p>
                        <p>
                            Final payment:{" "}
                            {version.terms_snapshot
                                .final_payment_days_before_event ?? 0}{" "}
                            days before the event.
                        </p>
                    </div>
                ) : (
                    <p className="mt-2 text-sm text-slate-600">
                        Business Policies will be frozen only at issuance.
                    </p>
                )}
            </div>
        </section>
    );
}

export function QuotationPaymentHistory({
    balance,
    payments,
    versions,
    canVerify,
    canViewEvidence,
}: {
    balance: QuotationPaymentBalance;
    payments: QuotationPayment[];
    versions: QuotationVersion[];
    canVerify: boolean;
    canViewEvidence: boolean;
}) {
    const reservationPaymentSatisfied =
        balance.reservation_remaining_minor <= 0;

    return (
        <section className="mt-6 rounded-2xl border bg-white p-5">
            <h2 className="font-semibold">Payments and balance</h2>
            <div className="mt-3 grid gap-2 text-sm sm:grid-cols-3">
                <p>
                    Reservation requirement:{" "}
                    <strong>
                        {formatPesos(
                            String(balance.reservation_required_minor),
                        )}
                    </strong>
                </p>
                <p>
                    Verified:{" "}
                    <strong>
                        {formatPesos(String(balance.verified_minor))}
                    </strong>
                </p>
                <p>
                    Outstanding:{" "}
                    <strong>
                        {formatPesos(String(balance.remaining_minor))}
                    </strong>
                </p>
            </div>
            <p className="mt-2 text-sm text-slate-600">
                {reservationPaymentSatisfied
                    ? "Reservation-payment requirement satisfied. Full payment may still remain outstanding."
                    : `${formatPesos(String(balance.reservation_remaining_minor))} remains before the reservation-payment requirement is satisfied.`}
                {balance.reservation_payment_overdue
                    ? " The reservation-payment deadline has passed; payment history remains available for review."
                    : ""}
                {balance.overpayment_minor > 0
                    ? ` Overpayment awaiting review: ${formatPesos(String(balance.overpayment_minor))}.`
                    : ""}
            </p>
            {payments.length === 0 ? (
                <p className="mt-4 text-sm text-slate-600">
                    No payment has been submitted for this quotation.
                </p>
            ) : (
                <ul className="mt-4 space-y-2 text-sm">
                    {payments.map((payment) => (
                        <li
                            key={payment.id}
                            className="rounded bg-slate-50 p-3"
                        >
                            <strong>
                                {payment.amount_minor
                                    ? formatPesos(payment.amount_minor)
                                    : "Awaiting verification"}
                            </strong>{" "}
                            {payment.method?.name
                                ? `via ${payment.method.name} · `
                                : ""}
                            <span className="capitalize">
                                {payment.status.replaceAll("_", " ")}
                            </span>{" "}
                            ·{" "}
                            {payment.paid_at
                                ? formatManilaTimestamp(payment.paid_at)
                                : "Receipt submitted"}
                            {payment.rejection_reason
                                ? ` — ${payment.rejection_reason}`
                                : ""}
                            {payment.reversal_reason
                                ? ` — ${payment.reversal_reason}`
                                : ""}
                            {payment.allocation_history.length > 0 && (
                                <span className="block text-xs text-slate-500">
                                    {payment.allocation_history
                                        .map(
                                            (allocation) =>
                                                `Version ${allocation.quotation_version.version_number}${allocation.superseded_at ? " (superseded)" : " (active)"}`,
                                        )
                                        .join(", ")}
                                </span>
                            )}
                            <PaymentEvidence
                                payment={payment}
                                canViewEvidence={canViewEvidence}
                            />
                            {canVerify && payment.status === "verified" && (
                                <button
                                    className="ml-3 rounded border px-2 py-1 text-xs"
                                    onClick={() => {
                                        const target = versions
                                            .filter(
                                                (version) => version.acceptance,
                                            )
                                            .at(-1)?.id;
                                        const reason = window.prompt(
                                            "Reason for reallocation",
                                        );
                                        if (target && reason)
                                            router.post(
                                                `/admin/payments/${payment.id}/reallocate`,
                                                {
                                                    quotation_version_id:
                                                        target,
                                                    reason,
                                                },
                                            );
                                    }}
                                >
                                    Allocate to latest accepted Version
                                </button>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

export function QuotationVersionHistory({
    versions,
}: {
    versions: QuotationVersion[];
}) {
    return (
        <section className="mt-6 rounded-2xl border bg-white p-5">
            <h2 className="font-semibold">Version history</h2>
            <ol className="mt-3 space-y-2 text-sm">
                {versions.map((version) => (
                    <li key={version.id} className="rounded-lg bg-slate-50 p-3">
                        v{version.version_number} · {version.status} ·{" "}
                        {formatPesos(version.total_minor)}
                        {version.issued_at && (
                            <span className="block text-slate-600">
                                Issued{" "}
                                {formatManilaTimestamp(version.issued_at)}
                            </span>
                        )}
                    </li>
                ))}
            </ol>
        </section>
    );
}

function OverviewDetail({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-slate-500">{label}</dt>
            <dd>{value}</dd>
        </div>
    );
}

function PaymentEvidence({
    payment,
    canViewEvidence,
}: {
    payment: QuotationPayment;
    canViewEvidence: boolean;
}) {
    if (!canViewEvidence || !payment.has_proof) return null;

    if (payment.evidence_versions.length === 0) {
        return (
            <span className="mt-2 flex gap-2">
                <a
                    href={`/admin/payments/${payment.id}/evidence/preview`}
                    target="_blank"
                    rel="noreferrer"
                    className="rounded border px-2 py-1 text-xs"
                >
                    Preview receipt
                </a>
                <a
                    href={`/admin/payments/${payment.id}/evidence`}
                    className="rounded border px-2 py-1 text-xs"
                >
                    Download receipt
                </a>
            </span>
        );
    }

    return (
        <span className="mt-2 flex flex-wrap gap-2">
            {payment.evidence_versions.map((evidence) => (
                <span key={evidence.id} className="flex items-center gap-1">
                    <a
                        href={`/admin/payments/${payment.id}/evidence/${evidence.id}/preview`}
                        target="_blank"
                        rel="noreferrer"
                        className="rounded border px-2 py-1 text-xs"
                    >
                        Preview receipt v{evidence.version_number}
                    </a>
                    <a
                        href={`/admin/payments/${payment.id}/evidence/${evidence.id}`}
                        className="rounded border px-2 py-1 text-xs"
                    >
                        Download
                    </a>
                </span>
            ))}
        </span>
    );
}
