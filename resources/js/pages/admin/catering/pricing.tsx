import { useState } from "react";
import AdminLayout from "../../../layouts/admin-layout";
import { useForm, Link } from "@inertiajs/react";
import { Button } from "../../../components/ui/button";
import { Field } from "../../../components/ui/field";
import { UnsavedChanges } from "../../../components/ui/unsaved-changes";
import { minorToPesoInput } from "../../../features/packages/money";
import { MainCateringWorkflowPage } from "../../../features/packages/components/main-catering-workflow-nav";
import type { PackageEditorProps } from "../../../features/packages/components/complete-package-form";

export default function CateringPricing({
    family,
    product,
    version,
    quantitySources,
}: PackageEditorProps) {
    const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false);
    if (!product || !version)
        throw new Error("A Draft is required for package pricing.");
    const form = useForm({
        base_price_pesos: minorToPesoInput(version.base_price_minor),
        price_basis: "per_pax" as const,
        selection_policy: "uniform" as const,
        minimum_quantity: String(version.minimum_quantity),
        quantity_step: String(version.quantity_step),
        maximum_quantity: version.maximum_quantity?.toString() ?? "",
        estimated_capacity: "",
        draft_revision: version.draft_revision,
    });
    const update = <K extends keyof typeof form.data>(
        key: K,
        value: (typeof form.data)[K],
    ) => {
        setHasUnsavedChanges(true);
        form.setData((current) => ({ ...current, [key]: value }));
        form.clearErrors(key);
    };
    const fields: [keyof typeof form.data, string, "text" | "number"][] = [
        ["base_price_pesos", "Price per pax (PHP)", "text"],
        ["minimum_quantity", "Minimum guests", "number"],
        ["quantity_step", "Guest quantity increment", "number"],
        ["maximum_quantity", "Maximum guests (optional)", "number"],
    ];

    return (
        <AdminLayout
            title={`Pricing · ${product.name}`}
            description={family.name}
            section="catering"
        >
            <MainCateringWorkflowPage
                family={family}
                product={product}
                current="pricing"
                title="Pricing & Guest Quantity"
                description="Set the commercial price and guest rules for this Draft. Laravel stores the price in centavos and validates these rules."
            >
                <UnsavedChanges dirty={form.isDirty || hasUnsavedChanges} />
                <form
                    className="max-w-2xl space-y-5 rounded-2xl border bg-white p-5 sm:p-6"
                    noValidate
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.clearErrors();
                        form.put(
                            `/admin/catering-packages/${product.id}/pricing`,
                        );
                    }}
                >
                    {quantitySources.length > 0 && (
                        <Field
                            id="copy-quantity"
                            label="Copy quantity settings from"
                        >
                            <select
                                id="copy-quantity"
                                value=""
                                onChange={(event) => {
                                    const source = quantitySources.find(
                                        (candidate) =>
                                            candidate.id ===
                                            Number(event.target.value),
                                    );
                                    if (!source) return;
                                    setHasUnsavedChanges(true);
                                    form.setData((current) => ({
                                        ...current,
                                        minimum_quantity: String(
                                            source.minimum_quantity,
                                        ),
                                        quantity_step: String(
                                            source.quantity_step,
                                        ),
                                        maximum_quantity:
                                            source.maximum_quantity?.toString() ??
                                            "",
                                    }));
                                }}
                            >
                                <option value="">
                                    Choose a package (optional)
                                </option>
                                {quantitySources.map((source) => (
                                    <option key={source.id} value={source.id}>
                                        {source.product.name}
                                    </option>
                                ))}
                            </select>
                            <p className="mt-1 text-xs text-slate-500">
                                Copies values only. Packages remain
                                independently editable.
                            </p>
                        </Field>
                    )}
                    {fields.map(([key, label, type]) => (
                        <Field
                            key={key}
                            id={`pricing-${key}`}
                            label={label}
                            error={form.errors[key]}
                        >
                            <input
                                id={`pricing-${key}`}
                                className={
                                    form.errors[key]
                                        ? "border-red-500"
                                        : undefined
                                }
                                required={key !== "maximum_quantity"}
                                type={type}
                                min={type === "number" ? "1" : undefined}
                                inputMode={
                                    key === "base_price_pesos"
                                        ? "decimal"
                                        : undefined
                                }
                                value={form.data[key]}
                                onChange={(event) =>
                                    update(key, event.target.value)
                                }
                                onBlur={() => form.clearErrors(key)}
                            />
                        </Field>
                    ))}
                    <p className="rounded-xl bg-slate-50 p-3 text-sm text-slate-600">
                        This Main Catering package is sold per guest. Food-level
                        premiums and serving measurements are not available.
                    </p>
                    <div className="flex flex-wrap justify-between gap-3 pt-2">
                        <Link
                            className="text-sm text-emerald-800 underline"
                            href={`/admin/catering-packages/${product.id}/details`}
                        >
                            Back to Details
                        </Link>
                        <Button disabled={form.processing}>
                            Save & Continue to Menu
                        </Button>
                    </div>
                </form>
            </MainCateringWorkflowPage>
        </AdminLayout>
    );
}
