import { useForm } from "@inertiajs/react";
import { Button } from "../../../components/ui/button";
import { Field } from "../../../components/ui/field";
import type { FoodCategory } from "../types";
import { catalogPaths } from "../paths";
export function CategoryForm({ category }: { category: FoodCategory | null }) {
    const form = useForm({
        name: category?.name ?? "",
        active: category?.active ?? true,
    });
    return (
        <form
            className="max-w-xl space-y-5 rounded-xl border border-stone-200 bg-white p-6"
            onSubmit={(event) => {
                event.preventDefault();
                if (category) form.put(catalogPaths.category(category.id));
                else form.post(catalogPaths.categories);
            }}
        >
            <Field id="name" label="Category name" error={form.errors.name}>
                <input
                    id="name"
                    value={form.data.name}
                    required
                    maxLength={255}
                    onChange={(e) => form.setData("name", e.target.value)}
                />
            </Field>
            <label className="flex items-center gap-2 text-sm">
                <input
                    type="checkbox"
                    checked={form.data.active}
                    onChange={(e) => form.setData("active", e.target.checked)}
                />
                Active category
            </label>
            {!form.data.active && (
                <p className="text-sm text-amber-800">
                    Inactive categories remain in history and make their dishes
                    unavailable for new catalog selection.
                </p>
            )}
            <Button disabled={form.processing}>
                {category ? "Save category" : "Create category"}
            </Button>
        </form>
    );
}
