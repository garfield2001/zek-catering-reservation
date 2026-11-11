import { useForm } from "@inertiajs/react";
import { useState } from "react";
import { Modal } from "../../../components/ui/modal";

export function PaymentReallocationDialog({
    paymentId,
    quotationVersionId,
}: {
    paymentId: number;
    quotationVersionId: number;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        quotation_version_id: quotationVersionId,
        reason: "",
    });

    const close = () => {
        if (form.processing) return;

        form.clearErrors();
        setOpen(false);
    };

    return (
        <>
            <button
                type="button"
                className="ml-3 rounded border px-2 py-1 text-xs font-semibold text-slate-800 hover:bg-slate-50"
                onClick={() => setOpen(true)}
            >
                Allocate to latest accepted Version
            </button>
            <Modal
                open={open}
                onClose={close}
                title="Reallocate verified payment"
                dirty={form.data.reason.trim().length > 0}
                busy={form.processing}
            >
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(`/admin/payments/${paymentId}/reallocate`, {
                            onSuccess: () => {
                                form.reset("reason");
                                setOpen(false);
                            },
                        });
                    }}
                >
                    <p className="text-sm leading-6 text-slate-600">
                        This explicitly supersedes the active allocation and
                        creates a new allocation for the latest accepted
                        quotation Version. It never changes the original
                        verified payment fact.
                    </p>
                    <label
                        htmlFor={`payment-${paymentId}-reallocation-reason`}
                        className="mt-5 block text-sm font-semibold text-slate-900"
                    >
                        Reason for reallocation
                    </label>
                    <textarea
                        id={`payment-${paymentId}-reallocation-reason`}
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
                                ? `payment-${paymentId}-reallocation-reason-error`
                                : undefined
                        }
                        className={`mt-2 w-full rounded-lg border px-3 py-2 text-sm ${form.errors.reason ? "border-rose-600" : "border-slate-300"}`}
                    />
                    {form.errors.reason && (
                        <p
                            id={`payment-${paymentId}-reallocation-reason-error`}
                            role="alert"
                            className="mt-2 text-sm text-rose-700"
                        >
                            {form.errors.reason}
                        </p>
                    )}
                    <div className="mt-6 flex flex-wrap justify-end gap-3">
                        <button
                            type="button"
                            disabled={form.processing}
                            onClick={close}
                            className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700"
                        >
                            Keep allocation
                        </button>
                        <button
                            disabled={form.processing}
                            className="rounded-lg bg-emerald-800 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"
                        >
                            {form.processing ? "Saving…" : "Reallocate payment"}
                        </button>
                    </div>
                </form>
            </Modal>
        </>
    );
}
