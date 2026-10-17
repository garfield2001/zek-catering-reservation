import { useDialogForm } from "../../../components/ui/dialog-form-guard";
import { DeactivationImpact } from "./deactivation-impact";
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
    workspace = false,
    onSaved,
    returnProductId,
    initialCategoryId,
    editorFamilyId,
    onCreated,
    onPersisted,
}: {
    dish: FoodItem | null;
    categories: FoodCategory[];
    families?: Collection[];
    workspace?: boolean;
    returnProductId?: number;
    initialCategoryId?: number;
    editorFamilyId?: number;
    onSaved?: () => void;
    onCreated?: (food: FoodItem & { category: FoodCategory }) => void;
    onPersisted?: (food: FoodItem & { category: FoodCategory }) => void;
}) {
    const { auth } = usePage<SharedProps>().props;
    const canCreateCategory =
        auth?.permissions.includes("catalog.manage") ?? false;
    const [newCategory, setNewCategory] = useState(false);
    const [imageRevision, setImageRevision] = useState(0);
    const [saving, setSaving] = useState(false);
    const [saveError, setSaveError] = useState("");
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
    });
    const processing = form.processing || saving;
    useDialogForm(form.isDirty, processing);
    return (
        <form
            className="space-y-5"
            onSubmit={async (event) => {
                event.preventDefault();
                event.stopPropagation();
                if (processing) return;
                const another =
                    (event.nativeEvent as SubmitEvent).submitter?.getAttribute(
                        "value",
                    ) === "another";
                const afterSave = (page: {
                    props: Record<string, unknown>;
                }) => {
                    if (another && !dish) {
                        const available = page.props.categories as
                            FoodCategory[] | undefined;
                        const category = newCategory
                            ? available?.find(
                                  (item) =>
                                      item.name.toLocaleLowerCase() ===
                                      form.data.new_category_name
                                          .trim()
                                          .toLocaleLowerCase(),
                              )?.id
                            : form.data.category_id;
                        const reset = {
                            ...form.data,
                            name: "",
                            description: "",
                            image: null,
                            remove_image: false,
                            new_category_name: "",
                            category_id: category ?? form.data.category_id,
                        };
                        form.setData(reset);
                        form.setDefaults(reset);
                        form.clearErrors();
                        setNewCategory(false);
                        setImageRevision((value) => value + 1);
                    } else onSaved?.();
                };
                if (onPersisted || (onCreated && !dish)) {
                    setSaving(true);
                    setSaveError("");
                    form.clearErrors();
                    const body = new FormData();
                    if (dish) body.append("_method", "put");
                    for (const [key, value] of Object.entries({
                        ...form.data,
                        category_id: newCategory ? null : form.data.category_id,
                        new_category_name: newCategory
                            ? form.data.new_category_name
                            : null,
                    })) {
                        if (value instanceof File) body.append(key, value);
                        else if (value !== null && value !== undefined)
                            body.append(
                                key,
                                typeof value === "boolean"
                                    ? value
                                        ? "1"
                                        : "0"
                                    : String(value),
                            );
                    }
                    try {
                        const response = await fetch(
                            dish
                                ? `${catalogPaths.dishes}/${dish.id}`
                                : catalogPaths.dishes,
                            {
                                method: "POST",
                                credentials: "same-origin",
                                headers: {
                                    Accept: "application/json",
                                    "X-CSRF-TOKEN":
                                        document.querySelector<HTMLMetaElement>(
                                            'meta[name="csrf-token"]',
                                        )?.content ?? "",
                                },
                                body,
                            },
                        );
                        if (response.status === 422) {
                            const result = await response.json();
                            form.setError(
                                Object.fromEntries(
                                    Object.entries(result.errors ?? {}).map(
                                        ([key, errors]) => [
                                            key,
                                            (errors as string[]).join(" "),
                                        ],
                                    ),
                                ),
                            );
                            return;
                        }
                        if (!response.ok) {
                            setSaveError(
                                "Food could not be saved. Check your access and session before retrying. Your unsaved input is retained.",
                            );
                            return;
                        }
                        const result = await response.json();
                        (onPersisted ?? onCreated)?.(result.food);
                        afterSave({
                            props: {
                                categories: [
                                    ...categories,
                                    result.food.category,
                                ],
                            },
                        });
                    } catch {
                        setSaveError(
                            "Could not confirm the save. Check the connection before retrying.",
                        );
                    } finally {
                        setSaving(false);
                    }
                    return;
                }
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
                        onSuccess: afterSave,
                    });
                else
                    form.post(catalogPaths.dishes, {
                        forceFormData: true,
                        onSuccess: afterSave,
                    });
            }}
        >
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
