import { useForm } from "@inertiajs/react";
import AdminLayout from "../../../layouts/admin-layout";

type PaymentMethod = {
    id: number;
    name: string;
    instructions: string | null;
    destination_information: string | null;
    is_offline: boolean;
    is_active: boolean;
    display_order: number;
};

type MethodForm = {
    name: string;
    instructions: string;
    destination_information: string;
    is_offline: boolean;
    is_active: boolean;
    display_order: number;
};

export default function PaymentMethods({
    methods,
}: {
    methods: PaymentMethod[];
}) {
    const form = useForm<MethodForm>({
        name: "",
        instructions: "",
        destination_information: "",
        is_offline: false,
        is_active: true,
        display_order: methods.length,
    });

    return (
        <AdminLayout title="Payment methods" section="quotations">
            <p className="mb-5 text-sm text-stone-600">
                Only active online methods appear in Customer Progress. Review
                customer instructions and destination information carefully
                before activating a method.
            </p>
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post("/admin/payments/methods", {
                        onSuccess: () => form.reset(),
                    });
                }}
                className="grid gap-3 rounded-xl border bg-white p-5"
            >
                <h2 className="font-semibold">Add payment method</h2>
                <MethodFields form={form} />
                <button className="w-fit rounded-lg bg-emerald-800 px-4 py-2 font-semibold text-white">
                    Add payment method
                </button>
            </form>
            <ul className="mt-5 space-y-3">
                {methods.map((method) => (
                    <PaymentMethodEditor key={method.id} method={method} />
                ))}
            </ul>
        </AdminLayout>
    );
}

function PaymentMethodEditor({ method }: { method: PaymentMethod }) {
    const form = useForm<MethodForm>({
        name: method.name,
        instructions: method.instructions ?? "",
        destination_information: method.destination_information ?? "",
        is_offline: method.is_offline,
        is_active: method.is_active,
        display_order: method.display_order,
    });

    return (
        <li className="rounded-xl border bg-white p-5">
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(`/admin/payments/methods/${method.id}`);
                }}
                className="grid gap-3"
            >
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h2 className="font-semibold">{method.name}</h2>
                    <span className="rounded-full bg-stone-100 px-2 py-1 text-xs font-semibold">
                        {form.data.is_active ? "Active" : "Inactive"}
                    </span>
                </div>
                <MethodFields form={form} />
                <div className="flex flex-wrap gap-2">
                    <button className="rounded-lg bg-emerald-800 px-4 py-2 text-sm font-semibold text-white">
                        Save changes
                    </button>
                    <button
                        type="button"
                        className="rounded-lg border px-4 py-2 text-sm font-semibold"
                        onClick={() =>
                            form.setData("is_active", !form.data.is_active)
                        }
                    >
                        {form.data.is_active
                            ? "Prepare deactivation"
                            : "Prepare activation"}
                    </button>
                </div>
                <p className="text-xs text-stone-500">
                    Activation changes are applied only after Save changes.
                    Existing payment records remain unchanged.
                </p>
            </form>
        </li>
    );
}

function MethodFields({
    form,
}: {
    form: ReturnType<typeof useForm<MethodForm>>;
}) {
    return (
        <>
            <label>
                Name
                <input
                    required
                    className="mt-1 w-full"
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData("name", event.target.value)
                    }
                    aria-invalid={Boolean(form.errors.name)}
                />
                <FieldError error={form.errors.name} />
            </label>
            <label>
                Public instructions
                <textarea
                    className="mt-1 w-full"
                    value={form.data.instructions}
                    onChange={(event) =>
                        form.setData("instructions", event.target.value)
                    }
                    aria-invalid={Boolean(form.errors.instructions)}
                />
                <FieldError error={form.errors.instructions} />
            </label>
            <label>
                Destination information
                <textarea
                    className="mt-1 w-full"
                    value={form.data.destination_information}
                    onChange={(event) =>
                        form.setData(
                            "destination_information",
                            event.target.value,
                        )
                    }
                    aria-invalid={Boolean(form.errors.destination_information)}
                />
                <FieldError error={form.errors.destination_information} />
            </label>
            <div className="grid gap-3 sm:grid-cols-3">
                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={form.data.is_offline}
                        onChange={(event) =>
                            form.setData("is_offline", event.target.checked)
                        }
                    />
                    Offline only
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={form.data.is_active}
                        onChange={(event) =>
                            form.setData("is_active", event.target.checked)
                        }
                    />
                    Active
                </label>
                <label className="text-sm">
                    Display order
                    <input
                        required
                        min="0"
                        type="number"
                        className="mt-1 w-full"
                        value={form.data.display_order}
                        onChange={(event) =>
                            form.setData(
                                "display_order",
                                Number(event.target.value),
                            )
                        }
                        aria-invalid={Boolean(form.errors.display_order)}
                    />
                    <FieldError error={form.errors.display_order} />
                </label>
            </div>
        </>
    );
}

function FieldError({ error }: { error?: string }) {
    return error ? (
        <span role="alert" className="mt-1 block text-sm text-rose-700">
            {error}
        </span>
    ) : null;
}
