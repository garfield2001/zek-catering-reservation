import { useForm } from "@inertiajs/react";
import { useRef, useState } from "react";
import { Button } from "../../../components/ui/button";
import { Field } from "../../../components/ui/field";
import { Modal } from "../../../components/ui/modal";
import { UnsavedChanges } from "../../../components/ui/unsaved-changes";
import { FoodItemForm } from "../../catalog/components/food-item-form";
import type { Collection } from "../../catalog/types";
import type { PackageProduct } from "../types";
import type { MenuVersion, MenuFood, MenuCategory } from "./package-menu-form";
import { initialMenu, menuSummary, emptyFoodDetail } from "../menu-state";
import { MenuFields } from "./menu-fields";
import type { QuantitySource } from "./pricing-form";
import { PricingFields } from "./pricing-fields";
import { minorToPesoInput } from "../money";

export type PackageEditorProps = {
    family: Collection;
    product: PackageProduct | null;
    version: MenuVersion | null;
    categories: MenuCategory[];
    foods: MenuFood[];
    canPrice: boolean;
    canCreateFood: boolean;
    quantitySources: QuantitySource[];
};
const steps = ["Details", "Menu", "Pricing & Quantity", "Review & Save"];
export function CompletePackageForm({
    family,
    product,
    version,
    categories,
    foods,
    canPrice,
    canCreateFood,
    quantitySources,
}: PackageEditorProps) {
    const main = family.behavior_type === "main_package",
        bundle = family.behavior_type === "fixed_bundle";
    const form = useForm({
        ...initialMenu(version),
        name: product?.name ?? "",
        description: product?.description ?? "",
        active: product?.active ?? true,
        family_id: family.id,
        creation_token: product ? undefined : crypto.randomUUID(),
        version_id: version?.id,
        draft_revision: version?.draft_revision,
        base_price_pesos: version
            ? minorToPesoInput(version.base_price_minor)
            : "",
        price_basis:
            version?.price_basis ??
            ((main
                ? "per_pax"
                : family.behavior_type === "packed_meal"
                  ? "per_pack"
                  : "per_set") as "per_pax" | "per_pack" | "per_set"),
        selection_policy:
            version?.selection_policy ?? ("uniform" as "uniform" | "split"),
        minimum_quantity: String(version?.minimum_quantity ?? 1),
        quantity_step: String(version?.quantity_step ?? 1),
        maximum_quantity: version?.maximum_quantity?.toString() ?? "",
        estimated_capacity: version?.estimated_capacity?.toString() ?? "",
    });
    const [step, setStep] = useState(0),
        [furthest, setFurthest] = useState(0),
        [notice, setNotice] = useState("");
    const [foodCategory, setFoodCategory] = useState<number | null | undefined>(
        undefined,
    );
    const panel = useRef<HTMLDivElement>(null);
    const summary = menuSummary(form.data, foods, bundle);
    const validStep = () => {
        const fields = panel.current?.querySelectorAll<
            HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement
        >("input, textarea, select");
        for (const field of fields ?? [])
            if (!field.disabled && !field.reportValidity()) return false;
        if (step === 1 && summary.impossible) {
            setNotice("Resolve the menu conflict below before continuing.");
            return false;
        }
        if (
            step === 2 &&
            form.data.maximum_quantity &&
            Number(form.data.maximum_quantity) <
                Number(form.data.minimum_quantity)
        ) {
            setNotice("Maximum purchase must be at least the minimum.");
            return false;
        }
        return true;
    };
    const go = (next: number) => {
        if (next > step && !validStep()) return;
        setNotice("");
        setStep(next);
        setFurthest(Math.max(furthest, next));
    };
    const save = () => {
        form.transform((data) => ({
            ...data,
            total_selection_count: bundle
                ? data.food_item_ids.length
                : data.total_selection_count,
            required_category_ids: bundle ? [] : data.required_category_ids,
            maximum_quantity: data.maximum_quantity || null,
            estimated_capacity: data.estimated_capacity || null,
            food_details: Object.fromEntries(
                data.food_item_ids.map((id) => {
                    const detail = data.food_details[id] ?? emptyFoodDetail();
                    return [
                        id,
                        main
                            ? { is_fixed: detail.is_fixed }
                            : {
                                  ...detail,
                                  included_quantity:
                                      detail.included_quantity || null,
                                  included_unit: detail.included_unit || null,
                              },
                    ];
                }),
            ),
        }));
        const options = {
            onError: () =>
                setNotice(
                    "Nothing was saved. Review the errors below and correct the applicable step.",
                ),
        };
        if (product)
            form.put(
                `/admin/packages/products/${product.id}/configuration`,
                options,
            );
        else form.post("/admin/packages/configurations", options);
    };
    return (
        <>
            <div className="rounded-2xl border bg-white p-4 sm:p-6">
                <p className="mb-4 text-sm text-stone-500">
                    {form.isDirty
                        ? "Unsaved changes — use Save Package after reviewing."
                        : "Review the complete package before saving."}{" "}
                    Saving does not publish.
                </p>
                {product?.current_version_id && (
                    <p className="mb-4 rounded-lg bg-emerald-50 p-3 text-sm">
                        The current Published Version remains unchanged. Saving
                        creates or updates a separate Draft.
                    </p>
                )}
                <nav
                    aria-label="Package setup steps"
                    className="mb-6 grid grid-cols-2 gap-2 sm:grid-cols-4"
                >
                    {steps.map((label, index) => (
                        <button
                            key={label}
                            type="button"
                            aria-current={step === index ? "step" : undefined}
                            disabled={!product && index > furthest + 1}
                            onClick={() => go(index)}
                            className={`rounded-lg px-3 py-3 text-sm font-semibold disabled:opacity-40 ${step === index ? "bg-emerald-800 text-white" : "bg-stone-100 text-stone-700"}`}
                        >
                            {index + 1}. {label}
                            <span className="block text-xs font-normal">
                                {index < furthest
                                    ? "Visited · not yet saved"
                                    : index > furthest + 1 && !product
                                      ? "Complete earlier steps first"
                                      : ""}
                            </span>
                        </button>
                    ))}
                </nav>
                {notice && (
                    <p
                        role="alert"
                        className="mb-4 rounded bg-amber-50 p-3 text-sm"
                    >
                        {notice}
                    </p>
                )}
                {Object.entries(form.errors).map(([key, error]) => (
                    <p
                        key={key}
                        role="alert"
                        className="mb-2 text-sm text-red-700"
                    >
                        {error}
                    </p>
                ))}
                <div ref={panel}>
                    {step === 0 && (
                        <section
                            className="space-y-5"
                            aria-label="Package details"
                        >
                            <h2 className="text-xl font-semibold">
                                Package Details
                            </h2>
                            <Field id="package-name" label="Package Name">
                                <input
                                    id="package-name"
                                    required
                                    maxLength={255}
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData("name", e.target.value)
                                    }
                                />
                            </Field>
                            <Field
                                id="package-description"
                                label="Description (optional)"
                            >
                                <textarea
                                    id="package-description"
                                    maxLength={5000}
                                    value={form.data.description}
                                    onChange={(e) =>
                                        form.setData(
                                            "description",
                                            e.target.value,
                                        )
                                    }
                                />
                            </Field>
                        </section>
                    )}
                    {step === 1 && (
                        <MenuFields
                            data={form.data}
                            onChange={(values) =>
                                form.setData((current) => ({
                                    ...current,
                                    ...values,
                                }))
                            }
                            categories={categories}
                            foods={foods}
                            main={main}
                            bundle={bundle}
                            packed={form.data.price_basis === "per_pack"}
                            canPrice={canPrice}
                            onAddFood={
                                canCreateFood
                                    ? (id) => setFoodCategory(id ?? null)
                                    : undefined
                            }
                        />
                    )}
                    {step === 2 && (
                        <PricingFields
                            sources={quantitySources}
                            data={form.data}
                            onChange={(values) =>
                                form.setData((current) => ({
                                    ...current,
                                    ...values,
                                }))
                            }
                            main={main}
                            configurable={family.behavior_type === "package"}
                            disabled={!canPrice}
                        />
                    )}
                    {step === 3 && (
                        <section
                            className="space-y-4"
                            aria-label="Review package"
                        >
                            <h2 className="text-xl font-semibold">
                                Review & Save
                            </h2>
                            <h3 className="font-semibold">
                                {form.data.name || "Package name needed"}
                            </h3>
                            <p>{form.data.description}</p>
                            <p>
                                PHP {form.data.base_price_pesos || "—"} per{" "}
                                {main
                                    ? "pax"
                                    : form.data.price_basis === "per_pack"
                                      ? "pack"
                                      : "package"}
                                . Minimum {form.data.minimum_quantity};
                                increment {form.data.quantity_step}; maximum{" "}
                                {form.data.maximum_quantity || "not set"}.
                            </p>
                            <p>
                                Exactly {summary.total} distinct foods. Fixed:{" "}
                                {summary.fixed.map((f) => f.name).join(", ") ||
                                    "None"}
                                .
                            </p>
                            <p>
                                Required categories:{" "}
                                {categories
                                    .filter((c) =>
                                        form.data.required_category_ids.includes(
                                            c.id,
                                        ),
                                    )
                                    .map((c) => c.name)
                                    .join(", ") || "None"}
                                .
                            </p>
                            <p>
                                Allowed foods:{" "}
                                {summary.allowed
                                    .map((f) => f.name)
                                    .join(", ") || "None"}
                                .
                            </p>
                            <p className="text-sm text-stone-500">
                                This is a preview of unsaved input. Laravel
                                validates and saves all steps together.
                                Publication requires a separate review and
                                confirmation.
                            </p>
                            <Button
                                type="button"
                                disabled={form.processing}
                                onClick={save}
                            >
                                {form.processing ? "Saving…" : "Save Package"}
                            </Button>
                        </section>
                    )}
                </div>
                <div className="mt-6 flex justify-between border-t pt-4">
                    <button
                        type="button"
                        disabled={step === 0 || form.processing}
                        onClick={() => go(step - 1)}
                        className="rounded-lg px-4 py-2 text-sm text-emerald-800 disabled:invisible"
                    >
                        Previous
                    </button>
                    {step < 3 && (
                        <Button type="button" onClick={() => go(step + 1)}>
                            Next: {steps[step + 1]}
                        </Button>
                    )}
                </div>
            </div>
            <Modal
                open={foodCategory !== undefined}
                onClose={() => setFoodCategory(undefined)}
                title="Add Food"
            >
                <FoodItemForm
                    dish={null}
                    categories={categories}
                    families={[family]}
                    initialCategoryId={foodCategory ?? undefined}
                    returnProductId={product?.id}
                    editorFamilyId={family.id}
                    onSaved={() => setFoodCategory(undefined)}
                />
            </Modal>
            <UnsavedChanges dirty={form.isDirty} />
        </>
    );
}
