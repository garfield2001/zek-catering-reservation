import { useForm, usePage } from "@inertiajs/react";
import { useState, useId } from "react";
import { Button } from "../../../components/ui/button";
import { Field } from "../../../components/ui/field";
import type { FoodCategory, FoodItem, Collection } from "../types";
import type { SharedProps } from "../../../types";
import { ImageUploadField } from "./image-upload-field";
import { catalogPaths } from "../paths";
export function FoodItemForm({
    dish,
    categories,
    families,
    workspace = false,
    onSaved,
    returnProductId,
    initialCategoryId,
    editorFamilyId,
}: {
    dish: FoodItem | null;
    categories: FoodCategory[];
    families: Collection[];
    workspace?: boolean;
    returnProductId?: number;
    initialCategoryId?: number;
    editorFamilyId?: number;
    onSaved?: () => void;
}) {
    const { auth } = usePage<SharedProps>().props;
    const canCreateCategory =
        auth?.permissions.includes("catalog.manage") ?? false;
    const [newCategory, setNewCategory] = useState(false);
    const [collectionSearch, setCollectionSearch] = useState("");
    const prefix = useId();
    const form = useForm({
        image: null as File | null,
        remove_image: false,
        name: dish?.name ?? "",
        description: dish?.description ?? "",
        category_id:
            dish?.category_id ??
            initialCategoryId ??
            categories.find((c) => c.active)?.id ??
            0,
        new_category_name: "",
        active: dish?.active ?? true,
        workspace,
        return_product_id: returnProductId,
        return_to_editor: !!editorFamilyId,
        editor_family_id: editorFamilyId,
        family_ids:
            dish?.eligible_families
                ?.filter((family) => family.pivot?.active)
                .map((family) => family.id) ??
            families
                .filter((family) =>
                    returnProductId || editorFamilyId ? true : !family.is_addon,
                )
                .map((family) => family.id),
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
            className="space-y-5"
            onSubmit={(event) => {
                event.preventDefault();
                form.transform((data) => ({
                    ...data,
                    _method: dish ? "put" : "post",
                    category_id: newCategory ? null : data.category_id,
                    new_category_name: newCategory
                        ? data.new_category_name
                        : null,
                }));
                if (dish)
                    form.post(catalogPaths.dish(dish.id), {
                        forceFormData: true,
                        onSuccess: onSaved,
                    });
                else
                    form.post(catalogPaths.dishes, {
                        forceFormData: true,
                        onSuccess: onSaved,
                    });
            }}
        >
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
            <fieldset>
                <legend className="text-sm font-medium">
                    Available for services
                </legend>
                <p className="mt-1 text-sm text-stone-500">
                    Choose where this food may be used. Catering Packages is
                    selected for new foods.
                </p>
                {families.length > 8 && (
                    <input
                        type="search"
                        aria-label="Find service"
                        placeholder="Find service"
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
                                {family.is_addon
                                    ? family.name
                                    : "Catering Packages"}
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
            <Button
                disabled={
                    form.processing ||
                    (!canCreateCategory && categories.length === 0)
                }
            >
                {dish ? "Save food item" : "Create food item"}
            </Button>
        </form>
    );
}
