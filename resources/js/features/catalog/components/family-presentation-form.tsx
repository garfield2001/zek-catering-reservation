import { useForm } from "@inertiajs/react";
import { Button } from "../../../components/ui/button";
import { Field } from "../../../components/ui/field";
import { catalogPaths } from "../paths";
import type { Family } from "../types";

export function FamilyPresentationForm({
    family,
    canManage,
}: {
    family: Family;
    canManage: boolean;
}) {
    const form = useForm({
        name: family.name,
        description: family.description ?? "",
        sort_order: family.sort_order,
        active: family.active,
    });
    if (!canManage)
        return (
            <section className="rounded-xl border border-stone-200 bg-white p-5">
                <h2 className="font-semibold">{family.name}</h2>
                <p className="text-xs text-stone-500">
                    {family.code} ·{" "}
                    {family.is_addon
                        ? "Optional add-on"
                        : "Primary catering family"}{" "}
                    · {family.active ? "Enabled" : "Disabled"}
                </p>
                {family.description && (
                    <p className="mt-2 text-sm text-stone-700">
                        {family.description}
                    </p>
                )}
            </section>
        );
    return (
        <form
            className="space-y-4 rounded-xl border border-stone-200 bg-white p-5"
            onSubmit={(event) => {
                event.preventDefault();
                form.put(catalogPaths.families + "/" + family.id);
            }}
        >
            <div>
                <h2 className="font-semibold">{family.name}</h2>
                <p className="text-xs text-stone-500">
                    Stable code: {family.code} ·{" "}
                    {family.is_addon
                        ? "Optional add-on"
                        : "Primary catering family"}
                </p>
            </div>
            <Field
                id={`family-name-${family.id}`}
                label="Display name"
                error={form.errors.name}
            >
                <input
                    id={`family-name-${family.id}`}
                    required
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData("name", event.target.value)
                    }
                />
            </Field>
            <Field
                id={`family-description-${family.id}`}
                label="Description"
                error={form.errors.description}
            >
                <textarea
                    id={`family-description-${family.id}`}
                    value={form.data.description}
                    onChange={(event) =>
                        form.setData("description", event.target.value)
                    }
                />
            </Field>
            <Field
                id={`family-order-${family.id}`}
                label="Display order"
                error={form.errors.sort_order}
            >
                <input
                    id={`family-order-${family.id}`}
                    type="number"
                    value={form.data.sort_order}
                    onChange={(event) =>
                        form.setData("sort_order", Number(event.target.value))
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
                Enabled for new draft configuration and selection
            </label>
            {Object.keys(form.errors).length > 0 && (
                <p role="alert" className="text-sm text-red-700">
                    Review the family display settings.
                </p>
            )}
            <Button disabled={form.processing}>Save family display</Button>
        </form>
    );
}
