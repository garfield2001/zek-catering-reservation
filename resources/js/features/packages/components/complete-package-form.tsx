import { useDialogForm } from "../../../components/ui/dialog-form-guard";
import { useForm } from "@inertiajs/react";
import { toast } from "sonner";
import { useRef, useState } from "react";
import { Button } from "../../../components/ui/button";
import { Field } from "../../../components/ui/field";
import { ConfirmDialog } from "../../../components/ui/confirm-dialog";
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
import type { FoodSizes } from "./food-size-fields";
import { FoodConfiguration } from "./food-configuration";
import type { MeasurementUnit } from "../../catalog/components/measurement-unit-form";
import {
    ServingDefaultFields,
    type ServingDefault,
} from "./serving-default-fields";

export type PackageEditorProps = {
    family: Collection;
    product: PackageProduct | null;
    version: MenuVersion | null;
    categories: MenuCategory[];
    foods: MenuFood[];
    canPrice: boolean;
    canPublish: boolean;
    canCreateFood: boolean;
    canCreateUnit: boolean;
    quantitySources: QuantitySource[];
    measurementUnits: MeasurementUnit[];
    foodSizes: FoodSizes;
    servingDefault: ServingDefault | null;
};
const steps = ["Details", "Menu", "Pricing & Quantity", "Review & Save"];
export function CompletePackageForm({
    family,
    product,
    version,
    categories: initialCategories,
    foods: initialFoods,
    canPrice,
    canPublish,
    canCreateFood,
    canCreateUnit,
    quantitySources,
    measurementUnits: initialMeasurementUnits,
    foodSizes,
    servingDefault,
    onSaved,
    initialStep = 0,
}: PackageEditorProps & {
    onSaved?: (id: number) => void;
    initialStep?: number;
}) {
    const [categories, setCategories] = useState(initialCategories);
    const [foods, setFoods] = useState(initialFoods);
    const [measurementUnits, setMeasurementUnits] = useState(
        initialMeasurementUnits,
    );
    const addUnit = canCreateUnit
        ? (unit: MeasurementUnit) =>
              setMeasurementUnits((current) => [
                  ...current.filter((item) => item.id !== unit.id),
                  unit,
              ])
        : undefined;
    const main = family.behavior_type === "main_package",
        bundle = family.behavior_type === "fixed_bundle";
    const form = useForm({
        ...initialMenu(version),
        food_sizes: foodSizes,
        serving_default: servingDefault,
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
    const [saving, setSaving] = useState(false);
    const processing = form.processing || saving;
    const [step, setStep] = useState(initialStep),
        [furthest, setFurthest] = useState(0),
        [notice, setNotice] = useState("");
    const [publishConfirmation, setPublishConfirmation] = useState(false);
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
    const save = async (publish = false) => {
        if (processing) return;
        const payload = (data: typeof form.data) => ({
            ...data,
            save_action: publish ? "publish" : "draft",
            ...(publish ? { confirm_publication: true } : {}),
            food_sizes: Object.fromEntries(
                Object.entries(data.food_sizes).filter(([id]) =>
                    data.food_item_ids.includes(Number(id)),
                ),
            ),
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
        });
        if (onSaved) {
            setSaving(true);
            form.clearErrors();
            try {
                const response = await fetch(
                    product
                        ? `/admin/packages/products/${product.id}/configuration`
                        : "/admin/packages/configurations",
                    {
                        method: product ? "PUT" : "POST",
                        credentials: "same-origin",
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                            "X-CSRF-TOKEN":
                                document.querySelector<HTMLMetaElement>(
                                    'meta[name="csrf-token"]',
                                )?.content ?? "",
                        },
                        body: JSON.stringify(payload(form.data)),
                    },
                );
                if (!response.ok) {
                    if (response.status === 422) {
                        const result = await response.json();
                        form.setError(
                            Object.fromEntries(
                                Object.entries(result.errors ?? {}).map(
                                    ([key, messages]) => [
                                        key,
                                        (messages as string[]).join(" "),
                                    ],
                                ),
                            ),
                        );
                    }
                    setNotice(
                        response.status === 403
                            ? "Your account cannot perform this action. Ask an Administrator to review access."
                            : response.status === 419
                              ? "Your session expired. Keep a copy of your unsaved input before signing in again."
                              : "Nothing was saved. Review the errors and correct the applicable step.",
                    );
                    setPublishConfirmation(false);
                    return;
                }
                const saved = await response.json();
                if (!Number.isInteger(saved.id)) {
                    throw new Error("Unexpected package save response.");
                }
                toast.success(
                    publish
                        ? "Package saved and published."
                        : "Draft saved. Published terms remain unchanged.",
                );
                onSaved(saved.id);
            } catch {
                setNotice(
                    "Could not confirm the save. Keep this editor open and check the connection before retrying.",
                );
            } finally {
                setSaving(false);
            }
            return;
        }
        form.transform(payload);
        const options = {
            onError: () => {
                setPublishConfirmation(false);
                setNotice(
                    "Nothing was saved. Review the errors below and correct the applicable step.",
                );
            },
        };
        if (product)
            form.put(
                `/admin/packages/products/${product.id}/configuration`,
                options,
            );
        else form.post("/admin/packages/configurations", options);
    };
    useDialogForm(form.isDirty, processing);
    return (
        <>
            <div className="rounded-2xl border bg-white p-4 sm:p-6">
                <p className="mb-4 text-sm text-stone-500">
                    {form.isDirty
                        ? "Unsaved changes — use Save Package after reviewing."
                        : "Review the complete package before saving."}{" "}
                    Save Draft does not publish.
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
                            disabled={
                                processing || (!product && index > furthest + 1)
                            }
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
                    {step === 1 && !main && (
                        <ServingDefaultFields
                            onUnitCreated={addUnit}
                            value={form.data.serving_default}
                            units={measurementUnits}
                            onChange={(value) =>
                                form.setData("serving_default", value)
                            }
                        />
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
                            renderFoodConfiguration={(food, fixed) => (
                                <FoodConfiguration
                                    food={food}
                                    fixed={fixed}
                                    bundle={bundle}
                                    packed={
                                        form.data.price_basis === "per_pack"
                                    }
                                    detail={
                                        form.data.food_details[food.id] ??
                                        emptyFoodDetail()
                                    }
                                    sizes={form.data.food_sizes[food.id]}
                                    servingDefault={form.data.serving_default}
                                    units={measurementUnits}
                                    canPrice={canPrice}
                                    onUnitCreated={addUnit}
                                    onChange={(detail, sizes) =>
                                        form.setData((current) => {
                                            const foodSizes = {
                                                ...current.food_sizes,
                                            };
                                            if (sizes)
                                                foodSizes[food.id] = sizes;
                                            else delete foodSizes[food.id];
                                            return {
                                                ...current,
                                                food_details: {
                                                    ...current.food_details,
                                                    [food.id]: detail,
                                                },
                                                food_sizes: foodSizes,
                                            };
                                        })
                                    }
                                />
                            )}
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
                                validates and saves all steps together. Use Save
                                Draft to continue later, or explicitly confirm
                                publication to make these terms live.
                            </p>
                            {!main && form.data.serving_default && (
                                <p>
                                    Default per food:{" "}
                                    {form.data.serving_default.quantity}{" "}
                                    {form.data.serving_default.size_label}{" "}
                                    {
                                        measurementUnits.find(
                                            (unit) =>
                                                unit.id ===
                                                Number(
                                                    form.data.serving_default
                                                        ?.measurement_unit_id,
                                                ),
                                        )?.abbreviation
                                    }
                                    . Foods with custom servings keep their
                                    overrides.
                                </p>
                            )}
                            {!main &&
                                summary.allowed.some(
                                    (food) => form.data.food_sizes[food.id],
                                ) && (
                                    <section
                                        aria-label="Review size choices"
                                        className="space-y-3 rounded-xl border p-4"
                                    >
                                        <h3 className="font-semibold">
                                            Size choices per purchased package
                                        </h3>
                                        {summary.allowed.map((food) => {
                                            const group =
                                                form.data.food_sizes[food.id];
                                            if (!group) return null;
                                            const unit = measurementUnits.find(
                                                (unit) =>
                                                    unit.id ===
                                                    Number(
                                                        group.measurement_unit_id,
                                                    ),
                                            );
                                            return (
                                                <div key={food.id}>
                                                    <h4 className="text-sm font-medium">
                                                        {food.name}
                                                    </h4>
                                                    <ul className="text-sm text-slate-600">
                                                        {group.choices.map(
                                                            (choice, index) => (
                                                                <li key={index}>
                                                                    {
                                                                        choice.quantity
                                                                    }{" "}
                                                                    {
                                                                        choice.size_label
                                                                    }{" "}
                                                                    {unit?.abbreviation ??
                                                                        "— choose an active unit"}{" "}
                                                                    ·{" "}
                                                                    {choice.is_default
                                                                        ? "Included default"
                                                                        : `+ PHP ${choice.extra_price_pesos} per package`}
                                                                </li>
                                                            ),
                                                        )}
                                                    </ul>
                                                </div>
                                            );
                                        })}
                                    </section>
                                )}
                            <Button
                                type="button"
                                disabled={processing}
                                onClick={() => save(false)}
                            >
                                {processing ? "Saving…" : "Save Draft"}
                            </Button>
                            {canPublish ? (
                                <Button
                                    type="button"
                                    disabled={processing || summary.impossible}
                                    onClick={() => setPublishConfirmation(true)}
                                >
                                    {product?.current_version_id
                                        ? "Publish Changes"
                                        : "Save & Publish"}
                                </Button>
                            ) : (
                                <p className="text-sm text-slate-600">
                                    Save a Draft and ask an authorized publisher
                                    to review it. Your account cannot publish
                                    packages.
                                </p>
                            )}
                        </section>
                    )}
                </div>
                <div className="mt-6 flex justify-between border-t pt-4">
                    <button
                        type="button"
                        disabled={step === 0 || processing}
                        onClick={() => go(step - 1)}
                        className="rounded-lg px-4 py-2 text-sm text-emerald-800 disabled:invisible"
                    >
                        Previous
                    </button>
                    {step < 3 && (
                        <Button
                            type="button"
                            disabled={processing}
                            onClick={() => go(step + 1)}
                        >
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
                    onCreated={(food) => {
                        setCategories((current) => [
                            ...current.filter(
                                (category) => category.id !== food.category.id,
                            ),
                            food.category,
                        ]);
                        if (food.active && food.category.active) {
                            setFoods((current) => [
                                ...current.filter(
                                    (item) => item.id !== food.id,
                                ),
                                food,
                            ]);
                            form.setData("food_item_ids", [
                                ...new Set([
                                    ...form.data.food_item_ids,
                                    food.id,
                                ]),
                            ]);
                        }
                        toast.success(
                            "Food Item created. Your package remains unsaved.",
                        );
                    }}
                    dish={null}
                    categories={categories}
                    families={[family]}
                    initialCategoryId={foodCategory ?? undefined}
                    returnProductId={product?.id}
                    editorFamilyId={family.id}
                    onSaved={() => setFoodCategory(undefined)}
                />
            </Modal>
            <ConfirmDialog
                open={publishConfirmation}
                onClose={() => setPublishConfirmation(false)}
                title="Publish this package?"
                description="All reviewed details, menu, prices and quantity rules will be saved together as the current Published Version. Previous published history remains unchanged."
                confirmLabel="Save & Publish"
                busy={processing}
                onConfirm={() => save(true)}
            />
            <UnsavedChanges dirty={form.isDirty} />
        </>
    );
}
