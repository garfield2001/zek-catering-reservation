import { useForm } from "@inertiajs/react";
import { Button } from "../../../components/ui/button";
import { Field } from "../../../components/ui/field";
import type { Category } from "../types";
import { catalogPaths } from "../paths";
export function CategoryForm({ category }: { category: Category | null }) {
    const form = useForm({
        code: category?.code ?? "",
        name: category?.name ?? "",
        sort_order: category?.sort_order ?? 0,
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
            <Field id="code" label="Stable code" error={form.errors.code}>
                <input
                    id="code"
                    value={form.data.code}
                    readOnly={!!category}
                    required
                    maxLength={80}
                    placeholder="pork"
                    onChange={(e) => form.setData("code", e.target.value)}
                />
            </Field>
            <Field id="name" label="Category name" error={form.errors.name}>
                <input
                    id="name"
                    value={form.data.name}
                    required
                    maxLength={255}
                    onChange={(e) => form.setData("name", e.target.value)}
                />
            </Field>
            <Field
                id="sort_order"
                label="Display order"
                error={form.errors.sort_order}
            >
                <input
                    id="sort_order"
                    type="number"
                    value={form.data.sort_order}
                    required
                    onChange={(e) =>
                        form.setData("sort_order", Number(e.target.value))
                    }
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
