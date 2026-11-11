import { router, useForm, usePage } from "@inertiajs/react";
import {
    QuotationPaymentHistory,
    QuotationSummary,
    QuotationVersionDetails,
    QuotationVersionHistory,
    type QuotationDetail,
    type QuotationPayment,
    type QuotationPaymentBalance,
    type QuotationVersion,
} from "../../../features/quotations/components/quotation-overview";
import { formatPesos } from "../../../features/packages/money";
import { CaseWorkflow } from "../../../features/reservations/components/case-workflow";
import AdminLayout from "../../../layouts/admin-layout";

type Version = QuotationVersion;

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
    quotation: QuotationDetail;
    canManage: boolean;
    canIssue: boolean;
    canRequestAdjustment: boolean;
    canApplyDirectAdjustment: boolean;
    canRecordDelivery: boolean;
    canWithdraw: boolean;
    canRecordOfflineAcceptance: boolean;
    canReviewAdjustment: boolean;
    paymentBalance: QuotationPaymentBalance | null;
    payments: QuotationPayment[];
    canVerifyPayments: boolean;
    canViewPaymentEvidence: boolean;
}) {
    const current = quotation.current_version;
    const accepted = Boolean(current.acceptance);
    const reservationPaymentSatisfied =
        paymentBalance !== null &&
        paymentBalance.reservation_remaining_minor <= 0;
    const paymentAwaitingVerification = payments.some((payment) =>
        ["submitted", "pending_verification"].includes(payment.status),
    );
    return (
        <AdminLayout
            title={quotation.reference}
            description="A quotation records commercial terms only; it is not a confirmed reservation or a payment record."
            section="quotations"
        >
            <CaseWorkflow
                steps={[
                    {
                        label: "Inquiry",
                        detail: quotation.reservation_request
                            ? quotation.reservation_request.reference
                            : "Manual quotation",
                        state: quotation.reservation_request
                            ? "complete"
                            : "upcoming",
                        href: quotation.reservation_request
                            ? `/admin/reservation-requests/${quotation.reservation_request.id}`
                            : undefined,
                    },
                    {
                        label: "Quotation",
                        detail: `${current.status} version ${current.version_number}`,
                        state: accepted ? "complete" : "current",
                    },
                    {
                        label: "Payment",
                        detail: !accepted
                            ? "Available after the customer accepts this quotation."
                            : paymentAwaitingVerification
                              ? "Payment submitted; awaiting verification."
                              : reservationPaymentSatisfied
                                ? "Reservation payment requirement satisfied."
                                : "Verified reservation payment still required.",
                        state: !accepted
                            ? "upcoming"
                            : paymentAwaitingVerification
                              ? "current"
                              : reservationPaymentSatisfied
                                ? "complete"
                                : "blocked",
                        href: "/admin/payments",
                    },
                    {
                        label: "Reservation",
                        detail: reservationPaymentSatisfied
                            ? "Ready for Administrator capacity review and confirmation."
                            : "Confirmation remains uncommitted until payment and capacity checks pass.",
                        state: reservationPaymentSatisfied
                            ? "current"
                            : "upcoming",
                    },
                ]}
            />
            <QuotationSummary
                quotation={quotation}
                canManage={canManage}
                issueControl={
                    current.status === "draft" && canIssue ? (
                        <IssueForm version={current} />
                    ) : undefined
                }
                withdrawControl={
                    current.status === "issued" && canWithdraw ? (
                        <WithdrawForm version={current} />
                    ) : undefined
                }
            />
            <QuotationVersionDetails version={current} />
            {paymentBalance ? (
                <QuotationPaymentHistory
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
            <QuotationVersionHistory versions={quotation.versions} />
        </AdminLayout>
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
