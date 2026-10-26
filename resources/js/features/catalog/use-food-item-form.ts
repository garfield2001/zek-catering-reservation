import { validationErrors } from "../../lib/validation-errors";
import { useForm, usePage } from "@inertiajs/react";
import { useState, type FormEvent } from "react";
import { useDialogForm } from "../../components/ui/dialog-form-guard";
import { csrfFetch, sessionFailureMessage } from "../../lib/http";
import type { SharedProps } from "../../types";
import type { FoodCategory, FoodItem, Collection } from "./types";
import { catalogPaths } from "./paths";
export type FoodItemFormProps = {
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
    onSavedAnother?: (food: FoodItem & { category: FoodCategory }) => void;
};
export function useFoodItemForm({
    dish,
    categories,
    workspace = false,
    onSaved,
    returnProductId,
    initialCategoryId,
    editorFamilyId,
    onCreated,
    onPersisted,
    onSavedAnother,
}: FoodItemFormProps) {
    const { auth } = usePage<SharedProps>().props;
    const canCreateCategory =
        auth?.permissions.includes("catalog.manage") ?? false;
    const [newCategory, setNewCategory] = useState(false);
    const [imageRevision, setImageRevision] = useState(0);
    const [saving, setSaving] = useState(false);
    const [saveError, setSaveError] = useState("");
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

    async function persist(another: boolean) {
        if (processing) return;
        const afterSave = (page: { props: Record<string, unknown> }) => {
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
                // The workspace intentionally refreshes its catalog props after
                // persistence. Queue the local reset after that refresh has
                // been requested so it cannot restore the just-saved values.
                window.setTimeout(() => {
                    form.setData(reset);
                    form.setDefaults(reset);
                    form.clearErrors();
                    setNewCategory(false);
                    setImageRevision((value) => value + 1);
                }, 0);
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
                const response = await csrfFetch(
                    dish
                        ? `${catalogPaths.dishes}/${dish.id}`
                        : catalogPaths.dishes,
                    {
                        method: "POST",
                        credentials: "same-origin",
                        headers: {
                            Accept: "application/json",
                        },
                        body,
                    },
                );
                if (response.status === 422) {
                    const result = await response.json();
                    form.setError(validationErrors(result));
                    return;
                }
                if (!response.ok || sessionFailureMessage(response)) {
                    setSaveError(
                        sessionFailureMessage(response) ??
                            "Food could not be saved. Check your access and session before retrying. Your unsaved input is retained.",
                    );
                    return;
                }
                const result = await response.json();
                if (another && !dish && onSavedAnother) {
                    onSavedAnother(result.food);
                    return;
                }
                (onPersisted ?? onCreated)?.(result.food);
                afterSave({
                    props: {
                        categories: [...categories, result.food.category],
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
            new_category_name: newCategory ? data.new_category_name : null,
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
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        event.stopPropagation();
        const another =
            (event.nativeEvent as SubmitEvent).submitter?.getAttribute(
                "value",
            ) === "another";
        void persist(another);
    }

    function saveAndAddAnother() {
        void persist(true);
    }

    return {
        form,
        newCategory,
        setNewCategory,
        imageRevision,
        processing,
        saveError,
        canCreateCategory,
        saveAndAddAnother,
        submit,
    };
}
