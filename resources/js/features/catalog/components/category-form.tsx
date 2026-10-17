import { useDialogForm } from "../../../components/ui/dialog-form-guard";
import { useId } from "react";
import { useForm } from "@inertiajs/react";
import { Button } from "../../../components/ui/button";
import { Field } from "../../../components/ui/field";
import type { FoodCategory } from "../types";
import { catalogPaths } from "../paths";
import { DeactivationImpact } from "./deactivation-impact";
export function CategoryForm({
    category,
    workspace = false,
    onSaved,
    returnProductId,
}: {
    category: FoodCategory | null;
    workspace?: boolean;
    returnProductId?: number;
    onSaved?: () => void;
}) {
    const prefix = useId();
    const form = useForm({
        name: category?.name ?? "",
        active: category?.active ?? true,
        workspace,
        return_product_id: returnProductId,
    });
    useDialogForm(form.isDirty, form.processing);
    return (
        <form
            className="space-y-5"
            onSubmit={(event) => {
                event.preventDefault();
                if (category)
                    form.put(catalogPaths.category(category.id), {
                        onSuccess: onSaved,
                        preserveScroll: true,
                    });
                else
                    form.post(catalogPaths.categories, {
                        onSuccess: onSaved,
                        preserveScroll: true,
                    });
            }}
        >
            <Field
                id={`${prefix}-name`}
                label="Category name"
                error={form.errors.name}
            >
                <input
                    id={`${prefix}-name`}
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
            {category && !form.data.active && (
                <DeactivationImpact kind="category" id={category.id} />
            )}
            <Button disabled={form.processing}>
                {category ? "Save category" : "Create category"}
            </Button>
        </form>
    );
}
