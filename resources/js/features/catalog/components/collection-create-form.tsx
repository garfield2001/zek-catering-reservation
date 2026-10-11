import { useForm } from "@inertiajs/react";
import { Button } from "../../../components/ui/button";
import { Field } from "../../../components/ui/field";

const behaviors = [
    {
        value: "package",
        label: "Package-based add-on",
        detail: "Fixed items, choices and per-set prices",
    },
    {
        value: "packed_meal",
        label: "Packed meals",
        detail: "Per-pack prices and optional split groups",
    },
    {
        value: "fixed_bundle",
        label: "Fixed bundles",
        detail: "Fixed inclusions and per-set prices",
    },
    {
        value: "direct_sale",
        label: "Direct-sale items",
        detail: "Catalog context now; listing manager comes later",
    },
] as const;

export function CollectionCreateForm({
    hasPrimary,
    onCancel,
    onCreated,
}: {
    hasPrimary: boolean;
    onCancel: () => void;
    onCreated: () => void;
}) {
    const form = useForm({
        kind: hasPrimary ? "addon" : "primary",
        behavior_type: hasPrimary ? "package" : "main_package",
        name: "",
        description: "",
        active: true,
    });
    return (
        <form
            className="space-y-5"
            onSubmit={(event) => {
                event.preventDefault();
                form.post("/admin/catalog/families", { onSuccess: onCreated });
            }}
        >
            <Field
                id="collection-kind"
                label="Collection role"
                error={form.errors.kind}
            >
                <select
                    id="collection-kind"
                    value={form.data.kind}
                    onChange={(event) => {
                        const kind = event.target.value;
                        form.setData({
                            ...form.data,
                            kind,
                            behavior_type:
                                kind === "primary" ? "main_package" : "package",
                        });
                    }}
                >
                    {!hasPrimary && (
                        <option value="primary">
                            Primary catering collection
                        </option>
                    )}
                    <option value="addon">Optional add-on</option>
                </select>
            </Field>
            {form.data.kind === "addon" && (
                <Field
                    id="collection-behavior"
                    label="Configuration type"
                    error={form.errors.behavior_type}
                >
                    <select
                        id="collection-behavior"
                        value={form.data.behavior_type}
                        onChange={(event) =>
                            form.setData("behavior_type", event.target.value)
                        }
                    >
                        {behaviors.map((behavior) => (
                            <option key={behavior.value} value={behavior.value}>
                                {behavior.label} — {behavior.detail}
                            </option>
                        ))}
                    </select>
                </Field>
            )}
            {form.data.kind === "primary" && (
                <p className="rounded-lg bg-emerald-50 p-3 text-sm text-emerald-900">
                    One primary catering collection is allowed. Add any number
                    of independently configured package variants after setup.
                </p>
            )}
            <Field
                id="collection-name"
                label="Customer-facing name"
                error={form.errors.name}
            >
                <input
                    id="collection-name"
                    required
                    maxLength={255}
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData("name", event.target.value)
                    }
                    placeholder="e.g. Catering Packages"
                />
            </Field>
            <Field
                id="collection-description"
                label="Description (optional)"
                error={form.errors.description}
            >
                <textarea
                    id="collection-description"
                    maxLength={5000}
                    value={form.data.description}
                    onChange={(event) =>
                        form.setData("description", event.target.value)
                    }
                />
            </Field>
            <label className="flex items-center gap-2 text-sm">
                <input
                    type="checkbox"
                    checked={form.data.active}
                    onChange={(event) =>
                        form.setData("active", event.target.checked)
                    }
                />
                Enabled for new catalog configuration
            </label>
            <div className="flex justify-end gap-3 border-t border-slate-100 pt-4">
                <button
                    type="button"
                    onClick={onCancel}
                    className="rounded-lg px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100"
                >
                    Cancel
                </button>
                <Button disabled={form.processing}>Create collection</Button>
            </div>
        </form>
    );
}
