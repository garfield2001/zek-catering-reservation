import { useState } from "react";
import AdminLayout from "../../../layouts/admin-layout";
import { Link, useForm } from "@inertiajs/react";
import { Button } from "../../../components/ui/button";
import { Modal } from "../../../components/ui/modal";
import { UnsavedChanges } from "../../../components/ui/unsaved-changes";
import { FoodItemForm } from "../../../features/catalog/components/food-item-form";
import type { FoodCategory, FoodItem } from "../../../features/catalog/types";
import { MainCateringMenuConfiguration } from "../../../features/packages/components/main-catering-menu-configuration";
import { MainCateringWorkflowPage } from "../../../features/packages/components/main-catering-workflow-nav";
import type { PackageEditorProps } from "../../../features/packages/components/complete-package-form";
import { initialMenu } from "../../../features/packages/menu-state";

export default function CateringMenu({
    family,
    product,
    version,
    categories: initialCategories,
    foods: initialFoods,
    canCreateFood,
}: PackageEditorProps) {
    if (!product || !version)
        throw new Error("A Draft is required for menu configuration.");
    const [categories, setCategories] = useState(initialCategories);
    const [foods, setFoods] = useState(initialFoods);
    const [addFoodOpen, setAddFoodOpen] = useState(false);
    const [initialCategoryId, setInitialCategoryId] = useState<number>();
    const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false);
    const form = useForm({
        ...initialMenu(version),
        draft_revision: version.draft_revision,
    });
    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.clearErrors();
        form.transform((data) => ({
            ...data,
            food_details: Object.fromEntries(
                data.food_item_ids.map((id) => [
                    id,
                    { is_fixed: data.food_details[id]?.is_fixed ?? false },
                ]),
            ),
        }));
        form.put(`/admin/catering-packages/${product.id}/menu`);
    };
    const addFood = (categoryId?: number) => {
        if (!canCreateFood) return;
        setInitialCategoryId(categoryId);
        setAddFoodOpen(true);
    };
    const onCreated = (food: FoodItem & { category: FoodCategory }) => {
        setCategories((current) =>
            current.some((category) => category.id === food.category.id)
                ? current
                : [...current, food.category].sort((a, b) =>
                      a.name.localeCompare(b.name),
                  ),
        );
        setFoods((current) => [
            ...current.filter((item) => item.id !== food.id),
            food,
        ]);
        setAddFoodOpen(false);
    };

    return (
        <AdminLayout
            title={`Menu · ${product.name}`}
            description={family.name}
            section="catering"
        >
            <MainCateringWorkflowPage
                family={family}
                product={product}
                current="menu"
                title="Menu Configuration"
                description="Choose eligible Food Items by category, then configure the saved package menu separately."
            >
                <UnsavedChanges dirty={form.isDirty || hasUnsavedChanges} />
                <form className="space-y-6" noValidate onSubmit={submit}>
                    <MainCateringMenuConfiguration
                        data={form.data}
                        foods={foods}
                        categories={categories}
                        errors={form.errors}
                        onChange={(values) => {
                            setHasUnsavedChanges(true);
                            form.setData((current) => ({
                                ...current,
                                ...values,
                            }));
                            for (const field of Object.keys(values))
                                form.clearErrors(
                                    field as keyof typeof form.data,
                                );
                        }}
                        onAddFood={addFood}
                    />
                    {!canCreateFood && (
                        <p className="rounded-xl bg-amber-50 p-3 text-sm text-amber-900">
                            Your account cannot add Food Items. Ask an
                            Administrator to add catalog items, then return to
                            this Draft.
                        </p>
                    )}
                    <div className="flex flex-wrap justify-between gap-3">
                        <Link
                            className="text-sm text-emerald-800 underline"
                            href={`/admin/catering-packages/${product.id}/pricing`}
                        >
                            Back to Pricing & Quantity
                        </Link>
                        <Button disabled={form.processing}>
                            Save & Continue to Review
                        </Button>
                    </div>
                </form>
                <Modal
                    open={addFoodOpen}
                    onClose={() => setAddFoodOpen(false)}
                    title="Add Food"
                    wide
                >
                    <FoodItemForm
                        dish={null}
                        categories={categories}
                        initialCategoryId={initialCategoryId}
                        editorFamilyId={family.id}
                        onCreated={onCreated}
                    />
                </Modal>
            </MainCateringWorkflowPage>
        </AdminLayout>
    );
}
