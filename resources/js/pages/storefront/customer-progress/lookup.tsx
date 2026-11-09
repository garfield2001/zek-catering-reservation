import { useForm } from "@inertiajs/react";
import StorefrontLayout from "../../../layouts/storefront-layout";

export default function CustomerProgressLookup() {
    const form = useForm({ reference: "", mobile_number: "", access_code: "" });
    const errors = form.errors as Record<string, string | undefined>;
    return (
        <StorefrontLayout title="Track Your Request">
            <div className="mx-auto max-w-xl px-4 py-16 sm:px-6">
                <p className="text-xs font-bold uppercase tracking-[.16em] text-amber-700">
                    Customer progress
                </p>
                <h1 className="mt-3 font-serif text-4xl font-bold text-emerald-950">
                    Track Your Request
                </h1>
                <p className="mt-4 leading-7 text-stone-600">
                    Enter the reference, mobile number, and private access code
                    shown when you submitted your inquiry. Save your access
                    code; it is not a mobile-number verification code.
                </p>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post("/track-request/access");
                    }}
                    className="mt-8 space-y-4 rounded-2xl border bg-white p-5"
                >
                    <Field
                        label="Request reference"
                        error={form.errors.reference}
                    >
                        <input
                            required
                            value={form.data.reference}
                            onChange={(event) =>
                                form.setData("reference", event.target.value)
                            }
                            className="mt-1 w-full"
                        />
                    </Field>
                    <Field
                        label="Registered mobile number"
                        error={form.errors.mobile_number}
                    >
                        <input
                            required
                            value={form.data.mobile_number}
                            onChange={(event) =>
                                form.setData(
                                    "mobile_number",
                                    event.target.value,
                                )
                            }
                            className="mt-1 w-full"
                        />
                    </Field>
                    <Field
                        label="Customer access code"
                        error={errors.access_code ?? errors.access}
                    >
                        <input
                            required
                            autoComplete="off"
                            value={form.data.access_code}
                            onChange={(event) =>
                                form.setData("access_code", event.target.value)
                            }
                            className="mt-1 w-full font-mono uppercase"
                        />
                    </Field>
                    <button
                        disabled={form.processing}
                        className="rounded-xl bg-emerald-800 px-5 py-3 text-sm font-semibold text-white"
                    >
                        View my request
                    </button>
                </form>
            </div>
        </StorefrontLayout>
    );
}

function Field({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <label className="block text-sm font-medium">
            {label}
            {children}
            {error && (
                <span role="alert" className="mt-1 block text-sm text-rose-700">
                    {error}
                </span>
            )}
        </label>
    );
}
