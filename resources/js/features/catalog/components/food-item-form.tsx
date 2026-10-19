import { useId } from "react";
import { DeactivationImpact } from "./deactivation-impact";
import { Button } from "../../../components/ui/button";
import { Field } from "../../../components/ui/field";
import { ImageUploadField } from "./image-upload-field";
import { useFoodItemForm, type FoodItemFormProps } from "../use-food-item-form";
export function FoodItemForm(props: FoodItemFormProps) {
    const { dish, categories, returnProductId, editorFamilyId } = props;
    const {
        form,
        newCategory,
        setNewCategory,
        imageRevision,
        processing,
        saveError,
        canCreateCategory,
        submit,
    } = useFoodItemForm(props);
    const prefix = useId();
    return (
        <form className="space-y-5" onSubmit={submit}>
            {saveError && (
                <p role="alert" className="text-sm text-red-700">
                    {saveError}
                </p>
            )}
            <Field
                id={`${prefix}-name`}
                label="Food item name"
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
            <Field
                id={`${prefix}-category_id`}
                label="Food category"
                error={form.errors.category_id}
            >
                <select
                    id={`${prefix}-category_id`}
                    value={newCategory ? "new" : form.data.category_id}
                    required
                    onChange={(e) => {
                        setNewCategory(e.target.value === "new");
                        if (e.target.value !== "new")
                            form.setData("category_id", Number(e.target.value));
                    }}
                >
                    <option value={0} disabled>
                        Choose a category
                    </option>
                    {canCreateCategory && (
                        <option value="new">Create new category…</option>
                    )}
                    {categories.map((category) => (
                        <option key={category.id} value={category.id}>
                            {category.name}
                            {category.active ? "" : " (inactive)"}
                        </option>
                    ))}
                </select>
            </Field>
            {newCategory && (
                <Field
                    id={`${prefix}-new-category`}
                    label="New category name"
                    error={form.errors.new_category_name}
                >
                    <input
                        id={`${prefix}-new-category`}
                        required
                        maxLength={255}
                        value={form.data.new_category_name}
                        onChange={(e) =>
                            form.setData("new_category_name", e.target.value)
                        }
                    />
                    <p className="text-xs text-slate-500">
                        Created together with this food when you save.
                    </p>
                </Field>
            )}
            <Field
                id={`${prefix}-description`}
                label="Description"
                error={form.errors.description}
            >
                <textarea
                    id={`${prefix}-description`}
                    className="min-h-24 w-full rounded-lg border border-stone-300 p-3 text-sm"
                    value={form.data.description}
                    maxLength={5000}
                    onChange={(e) =>
                        form.setData("description", e.target.value)
                    }
                />
            </Field>
            <ImageUploadField
                key={imageRevision}
                id={`${prefix}-image`}
                name={form.data.name}
                currentUrl={dish?.image_url}
                removing={form.data.remove_image}
                error={form.errors.image ?? form.errors.remove_image}
                onChange={(image, remove_image) =>
                    form.setData((current) => ({
                        ...current,
                        image,
                        remove_image,
                    }))
                }
            />
            <p className="text-sm text-slate-500">
                Active foods in active categories are available to every package
                builder. Each package chooses its own menu.
            </p>
            <label className="flex items-center gap-2 text-sm">
                <input
                    type="checkbox"
                    checked={form.data.active}
                    onChange={(e) => form.setData("active", e.target.checked)}
                />
                Active food item
            </label>
            {dish && !form.data.active && (
                <DeactivationImpact kind="food" id={dish.id} />
            )}
            {!form.data.active && (
                <p className="text-sm text-amber-800">
                    Deactivation preserves the food item and its serving
                    options, but prevents new catalog selection.
                </p>
            )}
            <Button
                disabled={
                    processing ||
                    (!canCreateCategory && categories.length === 0)
                }
            >
                {dish ? "Save food item" : "Create food item"}
            </Button>
            {!dish && (
                <Button
                    type="submit"
                    variant="secondary"
                    value="another"
                    disabled={processing}
                >
                    Save & Add Another
                </Button>
            )}
            {(returnProductId || editorFamilyId) && (
                <p className="text-xs text-slate-500">
                    Saving creates this Food Item in the catalog immediately.
                    Your package remains unsaved until you save it separately.
                </p>
            )}
        </form>
    );
}
