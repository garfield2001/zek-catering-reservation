import { useForm } from "@inertiajs/react";
import { useState } from "react";
import { Modal } from "../../../components/ui/modal";

type ReviewAction = "reject" | "reverse";

const content: Record<
    ReviewAction,
    { trigger: string; title: string; description: string; confirm: string }
> = {
    reject: {
        trigger: "Reject",
        title: "Reject payment",
        description:
            "The customer-visible payment record will be marked rejected. Provide a clear reason; submitted evidence history remains retained.",
        confirm: "Reject payment",
    },
    reverse: {
        trigger: "Reverse",
        title: "Reverse verified payment",
        description:
            "This is an accounting correction. The verified amount will no longer count toward the balance, while payment history remains retained.",
        confirm: "Reverse payment",
    },
};

export function PaymentReasonDialog({
    paymentId,
    action,
}: {
    paymentId: number;
    action: ReviewAction;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({ action, reason: "" });
    const copy = content[action];

    const close = () => {
        if (form.processing) return;

        form.clearErrors();
        setOpen(false);
    };

    return (
        <>
            <button
                type="button"
                className="rounded border px-2 py-1 text-xs font-semibold text-slate-800 hover:bg-slate-50"
                onClick={() => setOpen(true)}
            >
                {copy.trigger}
            </button>
            <Modal
                open={open}
                onClose={close}
                title={copy.title}
                dirty={form.data.reason.trim().length > 0}
                busy={form.processing}
            >
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(`/admin/payments/${paymentId}/review`, {
                            onSuccess: () => {
                                form.reset();
                                setOpen(false);
                            },
                        });
                    }}
                >
                    <p className="text-sm leading-6 text-slate-600">
                        {copy.description}
                    </p>
                    <label
                        htmlFor={`payment-${paymentId}-${action}-reason`}
                        className="mt-5 block text-sm font-semibold text-slate-900"
                    >
                        Reason
                    </label>
                    <textarea
                        id={`payment-${paymentId}-${action}-reason`}
                        required
                        rows={4}
                        value={form.data.reason}
                        onChange={(event) => {
                            form.setData("reason", event.target.value);
                            form.clearErrors("reason");
                        }}
                        aria-invalid={Boolean(form.errors.reason)}
                        aria-describedby={
                            form.errors.reason
                                ? `payment-${paymentId}-${action}-reason-error`
                                : undefined
                        }
                        className={`mt-2 w-full rounded-lg border px-3 py-2 text-sm ${form.errors.reason ? "border-rose-600" : "border-slate-300"}`}
                    />
                    {form.errors.reason && (
                        <p
                            id={`payment-${paymentId}-${action}-reason-error`}
                            role="alert"
                            className="mt-2 text-sm text-rose-700"
                        >
                            {form.errors.reason}
                        </p>
                    )}
                    {Object.entries(form.errors)
                        .filter(([field]) => field !== "reason")
                        .map(([field, error]) => (
                            <p
                                key={field}
                                role="alert"
                                className="mt-2 text-sm text-rose-700"
                            >
                                {error}
                            </p>
                        ))}
                    <div className="mt-6 flex flex-wrap justify-end gap-3">
                        <button
                            type="button"
                            disabled={form.processing}
                            onClick={close}
                            className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700"
                        >
                            Keep payment unchanged
                        </button>
                        <button
                            disabled={form.processing}
                            className="rounded-lg bg-rose-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"
                        >
                            {form.processing ? "Saving…" : copy.confirm}
                        </button>
                    </div>
                </form>
            </Modal>
        </>
    );
}
