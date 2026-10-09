import { useForm } from "@inertiajs/react";
import { Button } from "../../../components/ui/button";
import { Field } from "../../../components/ui/field";
import type { Dish, Family, Offering } from "../types";
import { catalogPaths } from "../paths";
export function OfferingForm({
    dish,
    offering,
    families,
}: {
    dish: Dish;
    offering: Offering | null;
    families: Family[];
}) {
    const form = useForm({
        family_id: offering?.family_id ?? families[0]?.id ?? 0,
        code: offering?.code ?? "",
        name: offering?.name ?? "",
        description: offering?.description ?? "",
        active: offering?.active ?? true,
    });
    return (
        <form
            className="max-w-xl space-y-5 rounded-xl border border-stone-200 bg-white p-6"
            onSubmit={(event) => {
                event.preventDefault();
                if (offering)
                    form.put(catalogPaths.offering(dish.id, offering.id));
                else form.post(catalogPaths.dish(dish.id) + "/offerings");
            }}
        >
            <Field
                id="family_id"
                label="Product family"
                error={form.errors.family_id}
            >
                {offering ? (
                    <input
                        id="family_id"
                        value={offering.family?.name ?? ""}
                        readOnly
                    />
                ) : (
                    <select
                        id="family_id"
                        value={form.data.family_id}
                        onChange={(e) =>
                            form.setData("family_id", Number(e.target.value))
                        }
                    >
                        {families.length === 0 && (
                            <option value={0}>No eligible families</option>
                        )}
                        {families.map((family) => (
                            <option key={family.id} value={family.id}>
                                {family.name}
                            </option>
                        ))}
                    </select>
                )}
            </Field>
            <Field id="code" label="Offering code" error={form.errors.code}>
                <input
                    id="code"
                    value={form.data.code}
                    readOnly={!!offering}
                    required
                    maxLength={80}
                    placeholder="regular_tray"
                    onChange={(e) => form.setData("code", e.target.value)}
                />
            </Field>
            <Field id="name" label="Preparation name" error={form.errors.name}>
                <input
                    id="name"
                    value={form.data.name}
                    required
                    maxLength={255}
                    onChange={(e) => form.setData("name", e.target.value)}
                />
            </Field>
            <Field
                id="description"
                label="Description"
                error={form.errors.description}
            >
                <textarea
                    id="description"
                    className="min-h-24 w-full rounded-lg border border-stone-300 p-3 text-sm"
                    value={form.data.description}
                    maxLength={5000}
                    onChange={(e) =>
                        form.setData("description", e.target.value)
                    }
                />
            </Field>
            <label className="flex items-center gap-2 text-sm">
                <input
                    type="checkbox"
                    checked={form.data.active}
                    onChange={(e) => form.setData("active", e.target.checked)}
                />
                Active offering
            </label>
            <Button
                disabled={
                    form.processing || (!offering && families.length === 0)
                }
            >
                {offering ? "Save offering" : "Create offering"}
            </Button>
        </form>
    );
}
