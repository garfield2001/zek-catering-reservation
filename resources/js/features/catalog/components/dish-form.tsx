import { useForm } from "@inertiajs/react";
import { useState } from "react";
import { Button } from "../../../components/ui/button";
import { Field } from "../../../components/ui/field";
import type { Category, Dish, Family } from "../types";
import { catalogPaths } from "../paths";
export function DishForm({
    dish,
    categories,
    families,
}: {
    dish: Dish | null;
    categories: Category[];
    families: Family[];
}) {
    const [collectionSearch, setCollectionSearch] = useState("");
    const form = useForm({
        code: dish?.code ?? "",
        name: dish?.name ?? "",
        description: dish?.description ?? "",
        category_id: dish?.category_id ?? categories[0]?.id ?? 0,
        active: dish?.active ?? true,
        family_ids:
            dish?.eligible_families
                ?.filter((family) => family.pivot?.active)
                .map((family) => family.id) ?? ([] as number[]),
    });
    const toggleFamily = (id: number, checked: boolean) =>
        form.setData(
            "family_ids",
            checked
                ? [...form.data.family_ids, id]
                : form.data.family_ids.filter((value) => value !== id),
        );
    return (
        <form
            className="max-w-2xl space-y-5 rounded-xl border border-stone-200 bg-white p-6"
            onSubmit={(event) => {
                event.preventDefault();
                if (dish) form.put(catalogPaths.dish(dish.id));
                else form.post(catalogPaths.dishes);
            }}
        >
            <Field
                id="code"
                label="Stable food item code"
                error={form.errors.code}
            >
                <input
                    id="code"
                    value={form.data.code}
                    readOnly={!!dish}
                    required
                    maxLength={80}
                    placeholder="chicken_adobo"
                    onChange={(e) => form.setData("code", e.target.value)}
                />
            </Field>
            <Field id="name" label="Food item name" error={form.errors.name}>
                <input
                    id="name"
                    value={form.data.name}
                    required
                    maxLength={255}
                    onChange={(e) => form.setData("name", e.target.value)}
                />
            </Field>
            <Field
                id="category_id"
                label="Food category"
                error={form.errors.category_id}
            >
                <select
                    id="category_id"
                    value={form.data.category_id}
                    required
                    onChange={(e) =>
                        form.setData("category_id", Number(e.target.value))
                    }
                >
                    {categories.map((category) => (
                        <option key={category.id} value={category.id}>
                            {category.name}
                            {category.active ? "" : " (inactive)"}
                        </option>
                    ))}
                </select>
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
            <fieldset>
                <legend className="text-sm font-medium">
                    Eligible collections
                </legend>
                <p className="mt-1 text-sm text-stone-500">
                    A food item can belong to several collections or none.
                    Removing eligibility retains any existing offerings as
                    unavailable history.
                </p>
                {families.length > 8 && (
                    <input
                        type="search"
                        aria-label="Find collection"
                        placeholder="Find collection"
                        value={collectionSearch}
                        onChange={(event) =>
                            setCollectionSearch(event.target.value)
                        }
                        className="mt-3 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm"
                    />
                )}
                <div className="mt-3 grid gap-2 sm:grid-cols-2">
                    {families
                        .filter((family) =>
                            family.name
                                .toLocaleLowerCase()
                                .includes(collectionSearch.toLocaleLowerCase()),
                        )
                        .map((family) => (
                            <label
                                key={family.id}
                                className="flex items-center gap-2 rounded-lg border border-stone-200 p-3 text-sm"
                            >
                                <input
                                    type="checkbox"
                                    checked={form.data.family_ids.includes(
                                        family.id,
                                    )}
                                    onChange={(e) =>
                                        toggleFamily(
                                            family.id,
                                            e.target.checked,
                                        )
                                    }
                                />
                                {family.name}
                            </label>
                        ))}
                </div>
                {form.errors.family_ids && (
                    <p role="alert" className="text-sm text-red-700">
                        {form.errors.family_ids}
                    </p>
                )}
            </fieldset>
            <label className="flex items-center gap-2 text-sm">
                <input
                    type="checkbox"
                    checked={form.data.active}
                    onChange={(e) => form.setData("active", e.target.checked)}
                />
                Active food item
            </label>
            {!form.data.active && (
                <p className="text-sm text-amber-800">
                    Deactivation preserves the food item and its serving
                    options, but prevents new catalog selection.
                </p>
            )}
            <Button disabled={form.processing || categories.length === 0}>
                {dish ? "Save food item" : "Create food item"}
            </Button>
        </form>
    );
}
