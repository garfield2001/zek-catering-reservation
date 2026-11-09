import { useForm } from "@inertiajs/react";
import { useState } from "react";
import { ConfirmDialog } from "../../../components/ui/confirm-dialog";
import { formatPesos as packageFormatPesos } from "../../packages/money";
import { formatManilaTimestamp } from "../../../lib/date-time";

export type CancellationRecord = {
    id: number;
    kind: "withdrawal" | "cancellation";
    status: string;
    reason: string;
    internal_note: string | null;
    requested_by_customer: boolean;
    requested_by_business: boolean;
    requested_at: string;
    review_note: string | null;
    reviewed_at: string | null;
    finalized_at: string | null;
    requester: string | null;
    reviewer: string | null;
    finalizer: string | null;
    financial_snapshot: Record<string, number>;
    refunds: Array<{
        id: number;
        payment_id: number;
        payment_amount_minor: string;
        amount_minor: string;
        status: string;
        reason: string;
        internal_note: string | null;
        review_note: string | null;
        requested_at: string;
        reviewed_at: string | null;
        completed_at: string | null;
        has_evidence: boolean;
        corrections: Array<{
            id: number;
            amount_minor: string;
            reason: string;
            created_at: string;
            actor: string;
        }>;
    }>;
};

function formatPesos(value: string | number): string {
    return packageFormatPesos(String(value));
}

export function CancellationManagement({
    quotationId,
    canRequest,
    canReview,
    cancellations,
    verifiedPayments,
}: {
    quotationId: number;
    canRequest: boolean;
    canReview: boolean;
    cancellations: CancellationRecord[];
    verifiedPayments: Array<{ id: number; amount_minor: string }>;
}) {
    const request = useForm({
        idempotency_key: "",
        reason: "",
        internal_note: "",
    });
    const [confirmBusinessCancellation, setConfirmBusinessCancellation] =
        useState(false);

    const submitBusinessCancellation = () => {
        request.transform((data) => ({
            ...data,
            idempotency_key: crypto.randomUUID(),
        }));
        request.post(
            `/admin/reservations/quotations/${quotationId}/business-cancellation`,
            {
                preserveScroll: true,
                onFinish: () => setConfirmBusinessCancellation(false),
            },
        );
    };

    return (
        <section className="mt-6 rounded-2xl border bg-white p-5">
            <h2 className="font-semibold">Cancellation and refunds</h2>
            <p className="mt-1 text-sm text-slate-600">
                Cancellation preserves quotations and payments. Only an
                Administrator&apos;s finalized decision releases confirmed event
                capacity. Refund approval never means money was sent.
            </p>
            {canRequest && (
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        request.transform((data) => ({
                            ...data,
                            idempotency_key: crypto.randomUUID(),
                        }));
                        request.post(
                            `/admin/reservations/quotations/${quotationId}/cancellations`,
                            { preserveScroll: true },
                        );
                    }}
                    className="mt-4 grid gap-3 rounded-xl border border-slate-200 p-4"
                >
                    <label className="text-sm font-medium">
                        Cancellation reason
                        <textarea
                            value={request.data.reason}
                            onChange={(event) =>
                                request.setData("reason", event.target.value)
                            }
                            className="mt-1 min-h-20 w-full"
                            aria-invalid={Boolean(request.errors.reason)}
                        />
                        <Error error={request.errors.reason} />
                    </label>
                    <label className="text-sm font-medium">
                        Internal context (optional)
                        <textarea
                            value={request.data.internal_note}
                            onChange={(event) =>
                                request.setData(
                                    "internal_note",
                                    event.target.value,
                                )
                            }
                            className="mt-1 min-h-16 w-full"
                        />
                    </label>
                    <div className="flex flex-wrap gap-3">
                        <button
                            disabled={request.processing}
                            className="w-fit rounded-lg border border-rose-800 px-4 py-2 text-sm font-semibold text-rose-900 disabled:opacity-50"
                        >
                            Submit cancellation for review
                        </button>
                        {canReview && (
                            <button
                                type="button"
                                disabled={
                                    request.processing || !request.data.reason
                                }
                                onClick={() =>
                                    setConfirmBusinessCancellation(true)
                                }
                                className="rounded-lg bg-rose-800 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                            >
                                Finalize ZEK-initiated cancellation
                            </button>
                        )}
                    </div>
                    <Error
                        error={
                            (request.errors as Record<string, string>)
                                .cancellation
                        }
                    />
                </form>
            )}
            <ConfirmDialog
                open={confirmBusinessCancellation}
                onClose={() => setConfirmBusinessCancellation(false)}
                title="Finalize a ZEK-initiated cancellation?"
                description="This records a business cancellation, releases confirmed capacity, and preserves the payment history for financial review. It cannot be silently undone."
                confirmLabel="Finalize cancellation"
                destructive
                busy={request.processing}
                onConfirm={submitBusinessCancellation}
            />
            {cancellations.length === 0 ? (
                <p className="mt-4 text-sm text-slate-600">
                    No cancellation or refund case has been recorded.
                </p>
            ) : (
                <ol className="mt-4 space-y-4">
                    {cancellations.map((cancellation) => (
                        <CancellationCard
                            key={cancellation.id}
                            cancellation={cancellation}
                            canReview={canReview}
                            verifiedPayments={verifiedPayments}
                        />
                    ))}
                </ol>
            )}
        </section>
    );
}

function CancellationCard({
    cancellation,
    canReview,
    verifiedPayments,
}: {
    cancellation: CancellationRecord;
    canReview: boolean;
    verifiedPayments: Array<{ id: number; amount_minor: string }>;
}) {
    const review = useForm({ approved: true, review_note: "" });
    const refund = useForm({
        payment_id: verifiedPayments[0]?.id?.toString() ?? "",
        amount_pesos: "",
        reason: "",
        internal_note: "",
        idempotency_key: "",
    });
    const isPending = cancellation.status === "pending_review";
    const canRefund = [
        "refund_under_review",
        "refund_approved",
        "refund_completed",
    ].includes(cancellation.status);

    return (
        <li className="rounded-xl border border-slate-200 p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 className="font-semibold text-slate-950">
                        {cancellation.kind === "withdrawal"
                            ? "Withdrawal"
                            : "Cancellation"}{" "}
                        #{cancellation.id}
                    </h3>
                    <p className="mt-1 text-sm text-slate-600">
                        {cancellation.status.replaceAll("_", " ")} · requested
                        by {cancellation.requester ?? "the customer"} on{" "}
                        {formatManilaTimestamp(cancellation.requested_at)}
                    </p>
                </div>
                <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-700">
                    {cancellation.status.replaceAll("_", " ")}
                </span>
            </div>
            <p className="mt-3 text-sm text-slate-800">
                <strong>Reason:</strong> {cancellation.reason}
            </p>
            <p className="mt-2 text-sm text-slate-600">
                Verified at request:{" "}
                {formatPesos(
                    String(cancellation.financial_snapshot.verified_minor ?? 0),
                )}
                {" · "}Pending verification:{" "}
                {formatPesos(
                    cancellation.financial_snapshot.pending_minor ?? 0,
                )}
            </p>
            {cancellation.internal_note && (
                <p className="mt-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-950">
                    Internal context: {cancellation.internal_note}
                </p>
            )}
            {isPending && canReview && (
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        review.post(
                            `/admin/cancellation-requests/${cancellation.id}/review`,
                            { preserveScroll: true },
                        );
                    }}
                    className="mt-4 grid gap-3 rounded-lg bg-slate-50 p-3"
                >
                    <label className="flex gap-2 text-sm font-medium">
                        <input
                            type="checkbox"
                            checked={review.data.approved}
                            onChange={(event) =>
                                review.setData("approved", event.target.checked)
                            }
                        />
                        Finalize this {cancellation.kind}
                    </label>
                    <label className="text-sm font-medium">
                        Administrator decision and reason
                        <textarea
                            value={review.data.review_note}
                            onChange={(event) =>
                                review.setData(
                                    "review_note",
                                    event.target.value,
                                )
                            }
                            className="mt-1 min-h-20 w-full"
                        />
                        <Error error={review.errors.review_note} />
                    </label>
                    <button
                        disabled={review.processing}
                        className="w-fit rounded-lg bg-emerald-800 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                    >
                        Save Administrator decision
                    </button>
                </form>
            )}
            {cancellation.review_note && (
                <p className="mt-3 text-sm text-slate-700">
                    <strong>Administrator decision:</strong>{" "}
                    {cancellation.review_note}
                </p>
            )}
            {canReview && canRefund && verifiedPayments.length > 0 && (
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        refund.transform((data) => ({
                            ...data,
                            payment_id: Number(data.payment_id),
                            idempotency_key: crypto.randomUUID(),
                        }));
                        refund.post(
                            `/admin/cancellation-requests/${cancellation.id}/refunds`,
                            { preserveScroll: true },
                        );
                    }}
                    className="mt-4 grid gap-3 rounded-lg border border-emerald-200 p-3 sm:grid-cols-2"
                >
                    <p className="sm:col-span-2 text-sm font-semibold text-emerald-950">
                        Record a refund for review
                    </p>
                    <label className="text-sm font-medium">
                        Verified payment
                        <select
                            value={refund.data.payment_id}
                            onChange={(event) =>
                                refund.setData("payment_id", event.target.value)
                            }
                            className="mt-1 w-full"
                        >
                            {verifiedPayments.map((payment) => (
                                <option key={payment.id} value={payment.id}>
                                    Payment #{payment.id} ·{" "}
                                    {formatPesos(payment.amount_minor)}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="text-sm font-medium">
                        Amount (PHP)
                        <input
                            inputMode="decimal"
                            value={refund.data.amount_pesos}
                            onChange={(event) =>
                                refund.setData(
                                    "amount_pesos",
                                    event.target.value,
                                )
                            }
                            className="mt-1 w-full"
                        />
                    </label>
                    <label className="text-sm font-medium sm:col-span-2">
                        Refund reason
                        <textarea
                            value={refund.data.reason}
                            onChange={(event) =>
                                refund.setData("reason", event.target.value)
                            }
                            className="mt-1 min-h-16 w-full"
                        />
                    </label>
                    <button
                        disabled={refund.processing}
                        className="w-fit rounded-lg border border-emerald-800 px-4 py-2 text-sm font-semibold text-emerald-900 disabled:opacity-50"
                    >
                        Submit refund for review
                    </button>
                    <Error
                        error={
                            refund.errors.amount_pesos ??
                            refund.errors.payment_id
                        }
                    />
                </form>
            )}
            {cancellation.refunds.length > 0 && (
                <ul className="mt-4 space-y-3">
                    {cancellation.refunds.map((refund) => (
                        <RefundCard
                            key={refund.id}
                            refund={refund}
                            canReview={canReview}
                        />
                    ))}
                </ul>
            )}
        </li>
    );
}

function RefundCard({
    refund,
    canReview,
}: {
    refund: CancellationRecord["refunds"][number];
    canReview: boolean;
}) {
    const review = useForm({ approved: true, review_note: "" });
    const complete = useForm({ note: "", evidence: null as File | null });
    const correction = useForm({ correction_pesos: "", reason: "" });
    const [confirmCompletion, setConfirmCompletion] = useState(false);

    const submitCompletion = () => {
        complete.post(`/admin/refunds/${refund.id}/complete`, {
            preserveScroll: true,
            forceFormData: true,
            onFinish: () => setConfirmCompletion(false),
        });
    };
    return (
        <li className="rounded-lg bg-slate-50 p-3 text-sm">
            <p className="font-semibold text-slate-950">
                Refund #{refund.id} · {formatPesos(refund.amount_minor)} ·{" "}
                {refund.status.replaceAll("_", " ")}
            </p>
            <p className="mt-1 text-slate-600">{refund.reason}</p>
            {refund.has_evidence && (
                <p className="mt-2 flex gap-3">
                    <a
                        className="font-medium text-emerald-800 underline"
                        href={`/admin/refunds/${refund.id}/evidence/preview`}
                        target="_blank"
                        rel="noreferrer"
                    >
                        Preview private evidence
                    </a>
                    <a
                        className="font-medium text-emerald-800 underline"
                        href={`/admin/refunds/${refund.id}/evidence`}
                    >
                        Download evidence
                    </a>
                </p>
            )}
            {canReview && refund.status === "pending_review" && (
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        review.post(`/admin/refunds/${refund.id}/review`, {
                            preserveScroll: true,
                        });
                    }}
                    className="mt-3 grid gap-2"
                >
                    <label className="flex gap-2">
                        <input
                            type="checkbox"
                            checked={review.data.approved}
                            onChange={(event) =>
                                review.setData("approved", event.target.checked)
                            }
                        />
                        Approve this refund
                    </label>
                    <textarea
                        value={review.data.review_note}
                        onChange={(event) =>
                            review.setData("review_note", event.target.value)
                        }
                        placeholder="Required when rejecting"
                        className="min-h-16 w-full"
                    />
                    <button className="w-fit rounded-lg border border-emerald-800 px-3 py-2 font-semibold text-emerald-900">
                        Save refund decision
                    </button>
                </form>
            )}
            {canReview && refund.status === "approved" && (
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        setConfirmCompletion(true);
                    }}
                    className="mt-3 grid gap-2"
                >
                    <p className="text-amber-900">
                        Only complete this after the money was actually returned
                        outside the system.
                    </p>
                    <textarea
                        value={complete.data.note}
                        onChange={(event) =>
                            complete.setData("note", event.target.value)
                        }
                        placeholder="Completion note (optional)"
                        className="min-h-16 w-full"
                    />
                    <input
                        type="file"
                        accept="application/pdf,image/jpeg,image/png"
                        onChange={(event) =>
                            complete.setData(
                                "evidence",
                                event.target.files?.[0] ?? null,
                            )
                        }
                    />
                    <button className="w-fit rounded-lg bg-emerald-800 px-3 py-2 font-semibold text-white">
                        Record completed refund
                    </button>
                </form>
            )}
            {canReview && refund.status === "completed" && (
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        correction.post(
                            `/admin/refunds/${refund.id}/corrections`,
                            { preserveScroll: true },
                        );
                    }}
                    className="mt-3 grid gap-2 sm:grid-cols-2"
                >
                    <input
                        value={correction.data.correction_pesos}
                        onChange={(event) =>
                            correction.setData(
                                "correction_pesos",
                                event.target.value,
                            )
                        }
                        placeholder="Signed PHP correction"
                    />
                    <input
                        value={correction.data.reason}
                        onChange={(event) =>
                            correction.setData("reason", event.target.value)
                        }
                        placeholder="Correction reason"
                    />
                    <button className="w-fit rounded-lg border border-slate-700 px-3 py-2 font-semibold text-slate-800">
                        Record correction
                    </button>
                </form>
            )}
            {refund.corrections.map((correction) => (
                <p key={correction.id} className="mt-2 text-xs text-slate-600">
                    Correction {formatPesos(correction.amount_minor)} by{" "}
                    {correction.actor}: {correction.reason}
                </p>
            ))}
            <ConfirmDialog
                open={confirmCompletion}
                onClose={() => setConfirmCompletion(false)}
                title="Record this refund as completed?"
                description="Use this only after the money was actually returned outside the application. The completed financial record cannot be silently edited."
                confirmLabel="Record completed refund"
                destructive
                busy={complete.processing}
                onConfirm={submitCompletion}
            />
        </li>
    );
}

function Error({ error }: { error?: string }) {
    return error ? (
        <p role="alert" className="text-sm text-rose-700">
            {error}
        </p>
    ) : null;
}
