import { useForm } from "@inertiajs/react";
import type { AcceptedQuotationVersion } from "../payment-types";

export function VerifyPayment({
    paymentId,
    quotationId,
    acceptedVersions,
}: {
    paymentId: number;
    quotationId: number | null;
    acceptedVersions: AcceptedQuotationVersion[];
}) {
    const form = useForm({
        action: "verify",
        verified_amount: "",
        customer_message: "",
        quotation_version_id: "",
    });
    const versions = acceptedVersions.filter(
        (version) => version.quotation_id === quotationId,
    );

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.post(`/admin/payments/${paymentId}/review`);
            }}
            className="flex flex-wrap items-end gap-2 rounded border bg-emerald-50 p-2"
        >
            <label className="text-xs font-medium">
                Amount received (PHP)
                <input
                    required
                    inputMode="decimal"
                    value={form.data.verified_amount}
                    onChange={(event) => {
                        form.setData("verified_amount", event.target.value);
                        form.clearErrors("verified_amount");
                    }}
                    className="mt-1 block w-32 rounded border px-2 py-1 text-sm"
                    aria-invalid={Boolean(form.errors.verified_amount)}
                />
            </label>
            {versions.length > 0 && (
                <label className="text-xs font-medium">
                    Allocate to Version
                    <select
                        value={form.data.quotation_version_id}
                        onChange={(event) => {
                            form.setData(
                                "quotation_version_id",
                                event.target.value,
                            );
                            form.clearErrors("quotation_version_id");
                        }}
                        aria-invalid={Boolean(form.errors.quotation_version_id)}
                        className="mt-1 block rounded border px-2 py-1 text-sm"
                    >
                        <option value="">Current accepted Version</option>
                        {versions.map((version) => (
                            <option key={version.id} value={version.id}>
                                Version {version.version_number}
                            </option>
                        ))}
                    </select>
                </label>
            )}
            <label className="text-xs font-medium">
                Customer message (optional)
                <input
                    value={form.data.customer_message}
                    onChange={(event) => {
                        form.setData("customer_message", event.target.value);
                        form.clearErrors("customer_message");
                    }}
                    className="mt-1 block w-44 rounded border px-2 py-1 text-sm"
                />
            </label>
            <button
                disabled={form.processing}
                className="rounded bg-emerald-800 px-2 py-1 text-xs text-white disabled:opacity-60"
            >
                {form.processing ? "Verifying..." : "Verify"}
            </button>
            <FormError error={form.errors.verified_amount} />
            <FormError error={form.errors.quotation_version_id} />
            <FormError error={form.errors.customer_message} />
            {Object.entries(form.errors)
                .filter(
                    ([field]) =>
                        ![
                            "verified_amount",
                            "quotation_version_id",
                            "customer_message",
                        ].includes(field),
                )
                .map(([field, error]) => (
                    <FormError key={field} error={error} />
                ))}
        </form>
    );
}

function FormError({ error }: { error?: string }) {
    return error ? (
        <span role="alert" className="w-full text-xs text-rose-700">
            {error}
        </span>
    ) : null;
}
