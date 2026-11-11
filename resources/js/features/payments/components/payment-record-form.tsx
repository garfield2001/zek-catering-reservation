import { useForm } from "@inertiajs/react";
import { useState } from "react";
import type { PaymentMethod } from "../payment-types";

const recordFields = [
    "quotation_id",
    "payment_method_id",
    "amount",
    "paid_at",
    "transaction_reference",
    "proof",
    "notes",
    "exception_reason",
];

export function PaymentRecordForm({
    methods,
    quotations,
    canRecordEarlyException,
}: {
    methods: PaymentMethod[];
    quotations: Array<{ id: number; reference: string }>;
    canRecordEarlyException: boolean;
}) {
    const [recordEarlyException, setRecordEarlyException] = useState(false);
    const form = useForm({
        quotation_id: "",
        payment_method_id: "",
        amount: "",
        paid_at: "",
        transaction_reference: "",
        notes: "",
        exception_reason: "",
        idempotency_key: crypto.randomUUID(),
        proof: null as File | null,
    });

    return (
        <form
            encType="multipart/form-data"
            onSubmit={(event) => {
                event.preventDefault();
                form.transform((data) => ({
                    ...data,
                    idempotency_key: crypto.randomUUID(),
                }));
                form.post("/admin/payments", { forceFormData: true });
            }}
            className="mb-5 grid gap-3 rounded-xl border bg-white p-5 sm:grid-cols-2"
        >
            <div className="sm:col-span-2">
                <h2 className="font-semibold">
                    Record cash or offline payment
                </h2>
                <p className="mt-1 text-sm text-slate-600">
                    Recording creates a payment history entry. It does not
                    verify the payment or confirm a reservation.
                </p>
            </div>
            {canRecordEarlyException && (
                <label className="sm:col-span-2 flex items-start gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={recordEarlyException}
                        onChange={(event) => {
                            setRecordEarlyException(event.target.checked);
                            if (event.target.checked)
                                form.setData("quotation_id", "");
                            form.clearErrors(
                                "quotation_id",
                                "exception_reason",
                            );
                        }}
                    />
                    <span>
                        Record an early Administrator exception before a
                        quotation is accepted
                    </span>
                </label>
            )}
            <label>
                Accepted quotation
                <select
                    required={!recordEarlyException}
                    disabled={recordEarlyException}
                    value={form.data.quotation_id}
                    onChange={(event) => {
                        form.setData("quotation_id", event.target.value);
                        form.clearErrors("quotation_id");
                    }}
                    aria-invalid={Boolean(form.errors.quotation_id)}
                    className={`mt-1 w-full ${form.errors.quotation_id ? "border-rose-600" : ""}`}
                >
                    <option value="">Select quotation</option>
                    {quotations.map((quotation) => (
                        <option key={quotation.id} value={quotation.id}>
                            {quotation.reference}
                        </option>
                    ))}
                </select>
                <FormError error={form.errors.quotation_id} />
            </label>
            <label>
                Payment method
                <select
                    required
                    value={form.data.payment_method_id}
                    onChange={(event) => {
                        form.setData("payment_method_id", event.target.value);
                        form.clearErrors("payment_method_id");
                    }}
                    aria-invalid={Boolean(form.errors.payment_method_id)}
                    className={`mt-1 w-full ${form.errors.payment_method_id ? "border-rose-600" : ""}`}
                >
                    <option value="">Select method</option>
                    {methods.map((method) => (
                        <option key={method.id} value={method.id}>
                            {method.name}
                            {method.is_offline ? " (offline)" : ""}
                        </option>
                    ))}
                </select>
                <FormError error={form.errors.payment_method_id} />
            </label>
            <label>
                Amount (PHP)
                <input
                    required
                    type="number"
                    min="0.01"
                    step="0.01"
                    value={form.data.amount}
                    onChange={(event) => {
                        form.setData("amount", event.target.value);
                        form.clearErrors("amount");
                    }}
                    aria-invalid={Boolean(form.errors.amount)}
                    className={`mt-1 w-full ${form.errors.amount ? "border-rose-600" : ""}`}
                />
                <FormError error={form.errors.amount} />
            </label>
            <label>
                Received at
                <input
                    required
                    type="datetime-local"
                    value={form.data.paid_at}
                    onChange={(event) => {
                        form.setData("paid_at", event.target.value);
                        form.clearErrors("paid_at");
                    }}
                    aria-invalid={Boolean(form.errors.paid_at)}
                    className={`mt-1 w-full ${form.errors.paid_at ? "border-rose-600" : ""}`}
                />
                <FormError error={form.errors.paid_at} />
            </label>
            <label>
                Reference (optional)
                <input
                    value={form.data.transaction_reference}
                    onChange={(event) => {
                        form.setData(
                            "transaction_reference",
                            event.target.value,
                        );
                        form.clearErrors("transaction_reference");
                    }}
                    aria-invalid={Boolean(form.errors.transaction_reference)}
                    className={`mt-1 w-full ${form.errors.transaction_reference ? "border-rose-600" : ""}`}
                />
                <FormError error={form.errors.transaction_reference} />
            </label>
            <label>
                Evidence (optional)
                <input
                    type="file"
                    accept="application/pdf,image/jpeg,image/png"
                    onChange={(event) => {
                        form.setData("proof", event.target.files?.[0] ?? null);
                        form.clearErrors("proof");
                    }}
                    aria-invalid={Boolean(form.errors.proof)}
                    className={`mt-1 w-full ${form.errors.proof ? "border-rose-600" : ""}`}
                />
                <FormError error={form.errors.proof} />
            </label>
            <label className="sm:col-span-2">
                Notes
                <textarea
                    value={form.data.notes}
                    onChange={(event) => {
                        form.setData("notes", event.target.value);
                        form.clearErrors("notes");
                    }}
                    aria-invalid={Boolean(form.errors.notes)}
                    className={`mt-1 w-full ${form.errors.notes ? "border-rose-600" : ""}`}
                />
                <FormError error={form.errors.notes} />
            </label>
            {recordEarlyException && (
                <label className="sm:col-span-2">
                    Reason for early exception
                    <textarea
                        required
                        value={form.data.exception_reason}
                        onChange={(event) => {
                            form.setData(
                                "exception_reason",
                                event.target.value,
                            );
                            form.clearErrors("exception_reason");
                        }}
                        aria-invalid={Boolean(form.errors.exception_reason)}
                        className={`mt-1 w-full ${form.errors.exception_reason ? "border-rose-600" : ""}`}
                    />
                    <FormError error={form.errors.exception_reason} />
                </label>
            )}
            {Object.entries(form.errors)
                .filter(([field]) => !recordFields.includes(field))
                .map(([field, error]) => (
                    <p
                        key={field}
                        role="alert"
                        className="sm:col-span-2 text-sm text-rose-700"
                    >
                        {error}
                    </p>
                ))}
            <button
                disabled={form.processing}
                className="w-fit rounded bg-emerald-800 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"
            >
                {form.processing ? "Recording..." : "Record payment"}
            </button>
        </form>
    );
}

function FormError({ error }: { error?: string }) {
    return error ? (
        <span role="alert" className="mt-1 block text-sm text-rose-700">
            {error}
        </span>
    ) : null;
}
