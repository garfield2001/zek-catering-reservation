import { useForm } from "@inertiajs/react";
import { useState } from "react";
import { Modal } from "../../../components/ui/modal";
import type { AcceptedQuotationVersion } from "../payment-types";

export function EarlyPaymentAllocationDialog({
    paymentId,
    versions,
}: {
    paymentId: number;
    versions: AcceptedQuotationVersion[];
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({ quotation_version_id: "", reason: "" });

    const close = () => {
        if (form.processing) return;

        form.clearErrors();
        setOpen(false);
    };

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                className="rounded border px-2 py-1 text-xs font-semibold text-slate-800 hover:bg-slate-50"
            >
                Allocate payment
            </button>
            <Modal
                open={open}
                onClose={close}
                title="Allocate early payment"
                dirty={
                    form.data.quotation_version_id !== "" ||
                    form.data.reason.trim().length > 0
                }
                busy={form.processing}
            >
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(`/admin/payments/${paymentId}/reallocate`, {
                            onSuccess: () => {
                                form.reset();
                                setOpen(false);
                            },
                        });
                    }}
                >
                    <p className="text-sm leading-6 text-slate-600">
                        Select the accepted quotation Version that owns this
                        payment. This creates an allocation history entry and
                        does not alter the original verified payment.
                    </p>
                    <label
                        htmlFor={`payment-${paymentId}-accepted-version`}
                        className="mt-5 block text-sm font-semibold text-slate-900"
                    >
                        Accepted quotation Version
                    </label>
                    <select
                        id={`payment-${paymentId}-accepted-version`}
                        required
                        value={form.data.quotation_version_id}
                        onChange={(event) => {
                            form.setData(
                                "quotation_version_id",
                                event.target.value,
                            );
                            form.clearErrors("quotation_version_id");
                        }}
                        aria-invalid={Boolean(form.errors.quotation_version_id)}
                        className={`mt-2 w-full rounded-lg border px-3 py-2 text-sm ${form.errors.quotation_version_id ? "border-rose-600" : "border-slate-300"}`}
                    >
                        <option value="">Select an accepted Version</option>
                        {versions.map((version) => (
                            <option key={version.id} value={version.id}>
                                {version.quotation.reference} v
                                {version.version_number}
                            </option>
                        ))}
                    </select>
                    <FormError error={form.errors.quotation_version_id} />
                    <label
                        htmlFor={`payment-${paymentId}-early-allocation-reason`}
                        className="mt-5 block text-sm font-semibold text-slate-900"
                    >
                        Reason for allocation
                    </label>
                    <textarea
                        id={`payment-${paymentId}-early-allocation-reason`}
                        required
                        rows={4}
                        value={form.data.reason}
                        onChange={(event) => {
                            form.setData("reason", event.target.value);
                            form.clearErrors("reason");
                        }}
                        aria-invalid={Boolean(form.errors.reason)}
                        className={`mt-2 w-full rounded-lg border px-3 py-2 text-sm ${form.errors.reason ? "border-rose-600" : "border-slate-300"}`}
                    />
                    <FormError error={form.errors.reason} />
                    <div className="mt-6 flex flex-wrap justify-end gap-3">
                        <button
                            type="button"
                            disabled={form.processing}
                            onClick={close}
                            className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700"
                        >
                            Keep payment unallocated
                        </button>
                        <button
                            disabled={form.processing}
                            className="rounded-lg bg-emerald-800 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"
                        >
                            {form.processing ? "Saving..." : "Allocate payment"}
                        </button>
                    </div>
                </form>
            </Modal>
        </>
    );
}

function FormError({ error }: { error?: string }) {
    return error ? (
        <p role="alert" className="mt-2 text-sm text-rose-700">
            {error}
        </p>
    ) : null;
}
