import { useForm } from "@inertiajs/react";

export function VerifyPayment({ paymentId }: { paymentId: number }) {
    const form = useForm({
        action: "verify",
        verified_amount: "",
        customer_message: "",
    });

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
                    onChange={(event) =>
                        form.setData("verified_amount", event.target.value)
                    }
                    className="mt-1 block w-32 rounded border px-2 py-1 text-sm"
                    aria-invalid={Boolean(form.errors.verified_amount)}
                />
            </label>
            <label className="text-xs font-medium">
                Customer message (optional)
                <input
                    value={form.data.customer_message}
                    onChange={(event) =>
                        form.setData("customer_message", event.target.value)
                    }
                    className="mt-1 block w-44 rounded border px-2 py-1 text-sm"
                />
            </label>
            <button
                disabled={form.processing}
                className="rounded bg-emerald-800 px-2 py-1 text-xs text-white"
            >
                Verify
            </button>
            {form.errors.verified_amount && (
                <span role="alert" className="w-full text-xs text-rose-700">
                    {form.errors.verified_amount}
                </span>
            )}
        </form>
    );
}
