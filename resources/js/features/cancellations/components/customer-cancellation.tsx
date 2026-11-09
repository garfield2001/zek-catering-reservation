import { useForm } from "@inertiajs/react";
import { useState } from "react";
import { ConfirmDialog } from "../../../components/ui/confirm-dialog";
import { formatPesos } from "../../packages/money";
import { formatManilaTimestamp } from "../../../lib/date-time";

type CustomerCancellation = {
    can_request: boolean;
    guidance: string;
    notice_days: number;
    requests: Array<{
        id: number;
        kind: "withdrawal" | "cancellation";
        status: string;
        requested_at: string;
        refunds: Array<{
            id: number;
            amount_minor: string;
            completed_at: string | null;
        }>;
    }>;
};

export function CustomerCancellationPanel({
    reference,
    cancellation,
}: {
    reference: string;
    cancellation: CustomerCancellation;
}) {
    const form = useForm({ idempotency_key: "", reason: "" });
    const [confirmRequest, setConfirmRequest] = useState(false);

    const submit = () => {
        form.transform((data) => ({
            ...data,
            idempotency_key: crypto.randomUUID(),
        }));
        form.post(`/track-request/${reference}/cancellations`, {
            preserveScroll: true,
            onFinish: () => setConfirmRequest(false),
        });
    };

    return (
        <section className="mt-5 rounded-2xl border bg-white p-5">
            <h2 className="font-semibold">Cancellation and refund status</h2>
            <p className="mt-2 text-sm text-stone-600">
                {cancellation.guidance}
            </p>
            <p className="mt-2 text-sm text-stone-600">
                Requests inside {cancellation.notice_days} days of the event are
                still accepted for review. They are not automatically declined,
                penalized, or refunded.
            </p>
            {cancellation.requests.length > 0 && (
                <ul className="mt-4 space-y-3">
                    {cancellation.requests.map((request) => (
                        <li
                            key={request.id}
                            className="rounded-lg bg-stone-50 p-3 text-sm"
                        >
                            <strong>
                                {request.kind === "withdrawal"
                                    ? "Withdrawal"
                                    : "Cancellation"}{" "}
                                #{request.id}
                            </strong>
                            <span className="ml-2 capitalize">
                                {request.status.replaceAll("_", " ")}
                            </span>
                            <p className="mt-1 text-stone-600">
                                Submitted{" "}
                                {formatManilaTimestamp(request.requested_at)}
                            </p>
                            {request.status === "pending_review" && (
                                <p className="mt-2 text-amber-900">
                                    Your cancellation request is awaiting
                                    review.
                                </p>
                            )}
                            {request.status === "refund_under_review" && (
                                <p className="mt-2 text-amber-900">
                                    Your refund is under review. Approval is not
                                    proof that money has been returned.
                                </p>
                            )}
                            {request.status === "refund_approved" && (
                                <p className="mt-2 text-amber-900">
                                    A refund was approved. ZEK Catering will
                                    record completion only after money is
                                    actually returned.
                                </p>
                            )}
                            {request.refunds.map((refund) => (
                                <p
                                    key={refund.id}
                                    className="mt-2 text-emerald-950"
                                >
                                    Your refund of{" "}
                                    {formatPesos(refund.amount_minor)} was
                                    recorded as completed
                                    {refund.completed_at
                                        ? ` on ${formatManilaTimestamp(refund.completed_at)}.`
                                        : "."}
                                </p>
                            ))}
                        </li>
                    ))}
                </ul>
            )}
            {cancellation.can_request && (
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        setConfirmRequest(true);
                    }}
                    className="mt-5 border-t pt-5"
                >
                    <label className="text-sm font-medium">
                        Why would you like to cancel?
                        <textarea
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData("reason", event.target.value)
                            }
                            className="mt-1 min-h-24 w-full"
                            aria-invalid={Boolean(form.errors.reason)}
                        />
                    </label>
                    {form.errors.reason && (
                        <p role="alert" className="mt-1 text-sm text-rose-700">
                            {form.errors.reason}
                        </p>
                    )}
                    {(form.errors as Record<string, string | undefined>)
                        .cancellation && (
                        <p role="alert" className="mt-1 text-sm text-rose-700">
                            {
                                (
                                    form.errors as Record<
                                        string,
                                        string | undefined
                                    >
                                ).cancellation
                            }
                        </p>
                    )}
                    <button
                        disabled={form.processing || !form.data.reason.trim()}
                        className="mt-3 rounded-lg border border-rose-800 px-4 py-2 text-sm font-semibold text-rose-900 disabled:opacity-50"
                    >
                        Request cancellation
                    </button>
                </form>
            )}
            <ConfirmDialog
                open={confirmRequest}
                onClose={() => setConfirmRequest(false)}
                title="Submit cancellation request?"
                description="Your request will be reviewed by ZEK Catering. Your reservation or quotation is unchanged until an Administrator records a decision. A refund is not promised by this request."
                confirmLabel="Submit for review"
                destructive
                busy={form.processing}
                onConfirm={submit}
            />
        </section>
    );
}
