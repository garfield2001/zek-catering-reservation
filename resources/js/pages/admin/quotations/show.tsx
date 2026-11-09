import { Link, router, useForm, usePage } from "@inertiajs/react";
import { formatPesos } from "../../../features/packages/money";
import AdminLayout from "../../../layouts/admin-layout";
import {
    formatManilaEvent,
    formatManilaTimestamp,
} from "../../../lib/date-time";

type Version = {
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

type Quote = {
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
    current_version: Version;
    versions: Version[];
};

export default function QuotationShow({
    quotation,
    canManage,
    canIssue,
    canRequestAdjustment,
    canApplyDirectAdjustment,
    canRecordDelivery,
    canWithdraw,
    canRecordOfflineAcceptance,
    canReviewAdjustment,
    paymentBalance,
    payments,
    canVerifyPayments,
    canViewPaymentEvidence,
}: {
    quotation: Quote;
    canManage: boolean;
    canIssue: boolean;
    canRequestAdjustment: boolean;
    canApplyDirectAdjustment: boolean;
    canRecordDelivery: boolean;
    canWithdraw: boolean;
    canRecordOfflineAcceptance: boolean;
    canReviewAdjustment: boolean;
    paymentBalance: {
        total_minor: number;
        verified_minor: number;
        remaining_minor: number;
        reservation_required_minor: number;
        reservation_remaining_minor: number;
        overpayment_minor: number;
        reservation_payment_overdue: boolean;
        final_payment_overdue: boolean;
    } | null;
    payments: Array<{
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
    }>;
    canVerifyPayments: boolean;
    canViewPaymentEvidence: boolean;
}) {
    const current = quotation.current_version;
    return (
        <AdminLayout
            title={quotation.reference}
            description="A quotation records commercial terms only; it is not a confirmed reservation or a payment record."
            section="quotations"
        >
            <div className="grid gap-6 lg:grid-cols-2">
                <section className="rounded-2xl border bg-white p-5">
                    <h2 className="font-semibold">Customer and event</h2>
                    <dl className="mt-3 space-y-2 text-sm">
                        <div>
                            <dt className="text-slate-500">Customer</dt>
                            <dd>{quotation.customer_name}</dd>
                        </div>
                        <div>
                            <dt className="text-slate-500">Contact</dt>
                            <dd>
                                {quotation.customer_mobile}{" "}
                                {quotation.customer_email
                                    ? `· ${quotation.customer_email}`
                                    : ""}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-slate-500">Event</dt>
                            <dd>
                                {formatManilaEvent(
                                    quotation.event_date,
                                    quotation.event_start_time,
                                )}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-slate-500">Venue</dt>
                            <dd>{quotation.venue_information ?? "Not set"}</dd>
                        </div>
                        {quotation.reservation_request && (
                            <div>
                                <dt className="text-slate-500">
                                    Source request
                                </dt>
                                <dd>
                                    <Link
                                        href={`/admin/reservation-requests/${quotation.reservation_request.id}`}
                                        className="font-semibold text-emerald-800 underline"
                                    >
                                        {
                                            quotation.reservation_request
                                                .reference
                                        }
                                    </Link>
                                </dd>
                            </div>
                        )}
                    </dl>
                </section>
                <section className="rounded-2xl border bg-white p-5">
                    <h2 className="font-semibold">
                        Current commercial version
                    </h2>
                    <p className="mt-2 text-3xl font-semibold text-emerald-950">
                        {formatPesos(current.total_minor)}
                    </p>
                    <p className="mt-1 text-sm text-slate-600">
                        v{current.version_number} · {current.status}
                    </p>
                    {current.status === "draft" && canIssue && (
                        <IssueForm version={current} />
                    )}
                    {current.status === "issued" && canWithdraw && (
                        <WithdrawForm version={current} />
                    )}
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
            <VersionDetails version={current} />
            {paymentBalance ? (
                <PaymentHistory
                    balance={paymentBalance}
                    payments={payments}
                    versions={quotation.versions}
                    canVerify={canVerifyPayments}
                    canViewEvidence={canViewPaymentEvidence}
                />
            ) : (
                <section className="mt-6 rounded-2xl border bg-white p-5 text-sm text-slate-600">
                    Payment details require the Payment Records permission. Ask
                    an Administrator to review access if this is part of your
                    role.
                </section>
            )}
            {current.status === "draft" && canApplyDirectAdjustment && (
                <DirectAdjustmentForm version={current} />
            )}
            {current.status === "draft" &&
                canRequestAdjustment &&
                !canApplyDirectAdjustment && (
                    <AdjustmentForm version={current} />
                )}
            {current.status === "issued" && canRecordDelivery && (
                <DeliveryForm version={current} />
            )}
            {current.status === "issued" && canRecordOfflineAcceptance && (
                <OfflineAcceptanceForm version={current} />
            )}
            {canReviewAdjustment &&
                current.adjustment_requests
                    .filter((item) => item.status === "pending")
                    .map((item) => (
                        <ReviewAdjustment key={item.id} request={item} />
                    ))}
            <section className="mt-6 rounded-2xl border bg-white p-5">
                <h2 className="font-semibold">Version history</h2>
                <ol className="mt-3 space-y-2 text-sm">
                    {quotation.versions.map((version) => (
                        <li
                            key={version.id}
                            className="rounded-lg bg-slate-50 p-3"
                        >
                            v{version.version_number} · {version.status} ·{" "}
                            {formatPesos(version.total_minor)}
                            {version.issued_at && (
                                <span className="block text-slate-600">
                                    Issued{" "}
                                    {formatManilaTimestamp(version.issued_at)}
                                </span>
                            )}
                            <span className="hidden">
                                {version.issued_at
                                    ? ` · issued ${version.issued_at}`
                                    : ""}
                            </span>
                        </li>
                    ))}
                </ol>
            </section>
        </AdminLayout>
    );
}

function PaymentHistory({
    balance,
    payments,
    versions,
    canVerify,
    canViewEvidence,
}: {
    balance: {
        total_minor: number;
        verified_minor: number;
        remaining_minor: number;
        reservation_required_minor: number;
        reservation_remaining_minor: number;
        overpayment_minor: number;
        reservation_payment_overdue: boolean;
        final_payment_overdue: boolean;
    };
    payments: Array<{
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
    }>;
    versions: Version[];
    canVerify: boolean;
    canViewEvidence: boolean;
}) {
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
                {balance.reservation_remaining_minor > 0
                    ? `${formatPesos(String(balance.reservation_remaining_minor))} remains before the reservation-payment requirement is satisfied.`
                    : "Reservation-payment requirement satisfied."}
                {balance.overpayment_minor > 0
                    ? ` Overpayment awaiting review: ${formatPesos(String(balance.overpayment_minor))}.`
                    : ""}
            </p>
            <ul className="mt-4 space-y-2 text-sm">
                {payments.map((payment) => (
                    <li key={payment.id} className="rounded bg-slate-50 p-3">
                        <strong>
                            {payment.amount_minor
                                ? formatPesos(payment.amount_minor)
                                : "Awaiting verification"}
                        </strong>{" "}
                        {payment.method?.name
                            ? `via ${payment.method.name} · `
                            : ""}
                        {payment.status.replaceAll("_", " ")} ·{" "}
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
                        {canViewEvidence &&
                            payment.evidence_versions.length > 0 && (
                                <span className="mt-2 flex flex-wrap gap-2">
                                    {payment.evidence_versions.map(
                                        (evidence) => (
                                            <span
                                                key={evidence.id}
                                                className="flex items-center gap-1"
                                            >
                                                <a
                                                    href={`/admin/payments/${payment.id}/evidence/${evidence.id}/preview`}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="rounded border px-2 py-1 text-xs"
                                                >
                                                    Preview receipt v
                                                    {evidence.version_number}
                                                </a>
                                                <a
                                                    href={`/admin/payments/${payment.id}/evidence/${evidence.id}`}
                                                    className="rounded border px-2 py-1 text-xs"
                                                >
                                                    Download
                                                </a>
                                            </span>
                                        ),
                                    )}
                                </span>
                            )}
                        {canViewEvidence &&
                            payment.has_proof &&
                            payment.evidence_versions.length === 0 && (
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
                            )}
                        {canVerify && payment.status === "verified" && (
                            <button
                                className="ml-3 rounded border px-2 py-1 text-xs"
                                onClick={() => {
                                    const target = versions
                                        .filter((version) => version.acceptance)
                                        .at(-1)?.id;
                                    const reason = window.prompt(
                                        "Reason for reallocation",
                                    );
                                    if (target && reason)
                                        router.post(
                                            `/admin/payments/${payment.id}/reallocate`,
                                            {
                                                quotation_version_id: target,
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
        </section>
    );
}

function VersionDetails({ version }: { version: Version }) {
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
                                line.line_type.replaceAll("_", " ")}{" "}
                            · quantity {line.quantity} ·{" "}
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

function IssueForm({ version }: { version: Version }) {
    const errors = useValidationErrors();
    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                router.post(`/admin/quotation-versions/${version.id}/issue`, {
                    valid_until: new FormData(event.currentTarget).get(
                        "valid_until",
                    ),
                });
            }}
            className="mt-4 space-y-2"
        >
            <label className="block text-sm font-medium">
                Valid until (optional override)
                <input
                    name="valid_until"
                    type="datetime-local"
                    aria-invalid={Boolean(errors.valid_until)}
                    className="mt-1 w-full"
                />
                <InlineFieldError error={errors.valid_until} />
            </label>
            <button className="rounded-lg bg-emerald-800 px-4 py-2 text-sm font-semibold text-white">
                Issue quotation
            </button>
            <p className="text-xs text-slate-500">
                The standard validity and payment requirements come from the
                current Business Policies and are frozen into this version.
            </p>
        </form>
    );
}
function WithdrawForm({ version }: { version: Version }) {
    const errors = useValidationErrors();
    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                router.post(
                    `/admin/quotation-versions/${version.id}/withdraw`,
                    { reason: new FormData(event.currentTarget).get("reason") },
                );
            }}
            className="mt-4 space-y-2"
        >
            <label className="block text-sm font-medium">
                Withdrawal reason
                <textarea
                    required
                    name="reason"
                    aria-invalid={Boolean(errors.reason)}
                    className="mt-1 w-full"
                />
                <InlineFieldError error={errors.reason} />
            </label>
            <button className="rounded-lg border border-rose-700 px-4 py-2 text-sm font-semibold text-rose-800">
                Withdraw quotation
            </button>
        </form>
    );
}
function AdjustmentForm({ version }: { version: Version }) {
    const errors = useValidationErrors();
    return (
        <section className="mt-6 rounded-2xl border bg-white p-5">
            <h2 className="font-semibold">Exceptional pricing</h2>
            <p className="mt-1 text-sm text-slate-600">
                A request needs separate approval before it changes the draft.
            </p>
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    const data = new FormData(event.currentTarget);
                    router.post(
                        `/admin/quotation-versions/${version.id}/adjustments`,
                        {
                            adjustment_amount: data.get("adjustment_amount"),
                            direction: data.get("direction"),
                            reason: data.get("reason"),
                        },
                    );
                }}
                className="mt-3 grid gap-3 sm:grid-cols-3"
            >
                <input
                    required
                    name="adjustment_amount"
                    placeholder="Peso amount"
                    aria-invalid={Boolean(errors.adjustment_amount)}
                />
                <InlineFieldError error={errors.adjustment_amount} />
                <select
                    name="direction"
                    aria-invalid={Boolean(errors.direction)}
                >
                    <option value="discount">Discount</option>
                    <option value="surcharge">Surcharge</option>
                </select>
                <InlineFieldError error={errors.direction} />
                <input
                    required
                    name="reason"
                    placeholder="Business reason"
                    aria-invalid={Boolean(errors.reason)}
                />
                <InlineFieldError error={errors.reason} />
                <button className="rounded-lg border border-emerald-800 px-4 py-2 text-sm font-semibold text-emerald-900">
                    Request approval
                </button>
            </form>
        </section>
    );
}
function DirectAdjustmentForm({ version }: { version: Version }) {
    const form = useForm({
        adjustment_amount: "",
        direction: "discount",
        reason: "",
    });
    const errors = Object.values(form.errors);
    const businessError = Object.entries(form.errors).find(
        ([field]) => field === "adjustment",
    )?.[1];
    return (
        <section className="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-5">
            <h2 className="font-semibold">Administrator adjustment</h2>
            <p className="mt-1 text-sm text-amber-950">
                Apply an exceptional discount, surcharge, override, or waiver
                directly. The reason and before-and-after totals are recorded in
                Activity History.
            </p>
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(
                        `/admin/quotation-versions/${version.id}/direct-adjustment`,
                    );
                }}
                className="mt-3 grid gap-3 sm:grid-cols-3"
            >
                <label className="text-sm">
                    Peso amount
                    <input
                        required
                        inputMode="decimal"
                        value={form.data.adjustment_amount}
                        onChange={(event) =>
                            form.setData(
                                "adjustment_amount",
                                event.target.value,
                            )
                        }
                        aria-invalid={Boolean(form.errors.adjustment_amount)}
                        aria-describedby={
                            form.errors.adjustment_amount
                                ? "direct-adjustment-amount-error"
                                : undefined
                        }
                        className="mt-1 w-full"
                    />
                    {form.errors.adjustment_amount && (
                        <span
                            id="direct-adjustment-amount-error"
                            role="alert"
                            className="mt-1 block text-rose-700"
                        >
                            {form.errors.adjustment_amount}
                        </span>
                    )}
                </label>
                <label className="text-sm">
                    Direction
                    <select
                        value={form.data.direction}
                        onChange={(event) =>
                            form.setData("direction", event.target.value)
                        }
                        className="mt-1 w-full"
                    >
                        <option value="discount">Discount</option>
                        <option value="surcharge">Surcharge / fee</option>
                    </select>
                </label>
                <label className="text-sm">
                    Reason
                    <textarea
                        required
                        value={form.data.reason}
                        onChange={(event) =>
                            form.setData("reason", event.target.value)
                        }
                        aria-invalid={Boolean(form.errors.reason)}
                        aria-describedby={
                            form.errors.reason
                                ? "direct-adjustment-reason-error"
                                : undefined
                        }
                        className="mt-1 w-full"
                    />
                    {form.errors.reason && (
                        <span
                            id="direct-adjustment-reason-error"
                            role="alert"
                            className="mt-1 block text-rose-700"
                        >
                            {form.errors.reason}
                        </span>
                    )}
                </label>
                {businessError && (
                    <p
                        role="alert"
                        className="sm:col-span-3 text-sm text-rose-700"
                    >
                        {businessError}
                    </p>
                )}
                {errors.length > 0 && (
                    <p className="sm:col-span-3 text-sm text-rose-700">
                        Correct the identified field before applying this
                        adjustment.
                    </p>
                )}
                <button className="rounded-lg bg-emerald-800 px-4 py-2 text-sm font-semibold text-white">
                    Apply Administrator adjustment
                </button>
            </form>
        </section>
    );
}
function ReviewAdjustment({
    request,
}: {
    request: Version["adjustment_requests"][number];
}) {
    return (
        <section className="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm">
            <p>
                <strong>Pending adjustment:</strong>{" "}
                {formatPesos(request.requested_adjustment_minor)} ·{" "}
                {request.reason} · requested by {request.requester.name}
            </p>
            <div className="mt-3 flex gap-2">
                <button
                    onClick={() =>
                        router.post(
                            `/admin/quotation-adjustments/${request.id}/review`,
                            { approved: true },
                        )
                    }
                    className="rounded-lg bg-emerald-800 px-3 py-2 font-semibold text-white"
                >
                    Approve
                </button>
                <button
                    onClick={() =>
                        router.post(
                            `/admin/quotation-adjustments/${request.id}/review`,
                            { approved: false },
                        )
                    }
                    className="rounded-lg border px-3 py-2 font-semibold"
                >
                    Decline
                </button>
            </div>
        </section>
    );
}
function DeliveryForm({ version }: { version: Version }) {
    const errors = useValidationErrors();
    return (
        <section className="mt-6 rounded-2xl border bg-white p-5">
            <h2 className="font-semibold">Delivery record</h2>
            <p className="mt-1 text-sm text-slate-600">
                Record a staff-performed delivery. This system does not
                automatically send SMS or email.
            </p>
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    const data = new FormData(event.currentTarget);
                    router.post(
                        `/admin/quotation-versions/${version.id}/delivery-events`,
                        {
                            channel: data.get("channel"),
                            destination_hint: data.get("destination_hint"),
                            note: data.get("note"),
                        },
                    );
                }}
                className="mt-3 grid gap-3 sm:grid-cols-3"
            >
                <select name="channel" aria-invalid={Boolean(errors.channel)}>
                    <option value="manual_link">Manual secure link</option>
                    <option value="sms">SMS sent externally</option>
                    <option value="email">Email sent externally</option>
                    <option value="printed">Printed copy</option>
                </select>
                <InlineFieldError error={errors.channel} />
                <input
                    name="destination_hint"
                    placeholder="Destination hint"
                    aria-invalid={Boolean(errors.destination_hint)}
                />
                <InlineFieldError error={errors.destination_hint} />
                <input
                    name="note"
                    placeholder="Optional note"
                    aria-invalid={Boolean(errors.note)}
                />
                <InlineFieldError error={errors.note} />
                <button className="rounded-lg border border-emerald-800 px-4 py-2 text-sm font-semibold text-emerald-900">
                    Record delivery
                </button>
            </form>
        </section>
    );
}
function OfflineAcceptanceForm({ version }: { version: Version }) {
    const errors = useValidationErrors();
    return (
        <section className="mt-6 rounded-2xl border bg-white p-5">
            <h2 className="font-semibold">Record offline acceptance</h2>
            <p className="mt-1 text-sm text-slate-600">
                Use only when acceptance was obtained outside this website. An
                attachment is optional evidence, stored privately.
            </p>
            <form
                encType="multipart/form-data"
                onSubmit={(event) => {
                    event.preventDefault();
                    router.post(
                        `/admin/quotation-versions/${version.id}/offline-acceptance`,
                        new FormData(event.currentTarget),
                    );
                }}
                className="mt-3 grid gap-3 sm:grid-cols-3"
            >
                <input
                    required
                    name="accepted_by_name"
                    placeholder="Customer name"
                    aria-invalid={Boolean(errors.accepted_by_name)}
                />
                <InlineFieldError error={errors.accepted_by_name} />
                <input
                    name="evidence"
                    type="file"
                    accept="application/pdf,image/jpeg,image/png"
                    aria-invalid={Boolean(errors.evidence)}
                />
                <InlineFieldError error={errors.evidence} />
                <button className="rounded-lg border border-emerald-800 px-4 py-2 text-sm font-semibold text-emerald-900">
                    Record acceptance
                </button>
            </form>
        </section>
    );
}

function useValidationErrors(): Record<string, string> {
    return usePage<{ errors: Record<string, string> }>().props.errors ?? {};
}

function InlineFieldError({ error }: { error?: string }) {
    return error ? (
        <span role="alert" className="mt-1 block text-sm text-rose-700">
            {error}
        </span>
    ) : null;
}
