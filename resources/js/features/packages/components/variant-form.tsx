import { useForm } from "@inertiajs/react";
import { useEffect } from "react";
import { Button } from "../../../components/ui/button";
import { Field } from "../../../components/ui/field";
import type { CollectionOrderingDefault } from "../../catalog/types";
import { packagePaths } from "../paths";
import type { VariantVersion, PackageProduct, PackageVariant } from "../types";
import { minorToPesoInput } from "../money";

export function VariantForm({
    product,
    variant,
    version,
    defaults,
}: {
    product: PackageProduct;
    variant: PackageVariant | null;
    version: VariantVersion | null;
    defaults: CollectionOrderingDefault;
}) {
    const form = useForm({
        name: variant?.name ?? "",
        description: variant?.description ?? "",
        active: variant?.active ?? true,
        base_price_pesos: minorToPesoInput(version?.base_price_minor ?? "0"),
        selection_policy: version?.selection_policy ?? "uniform",
        minimum_override: version?.minimum_override ?? (null as number | null),
        step_override: version?.step_override ?? (null as number | null),
        maximum_override_enabled: version?.maximum_override_enabled ?? false,
        maximum_override: version?.maximum_override ?? (null as number | null),
        ...(version ? { draft_revision: version.draft_revision } : {}),
    });
    const basis =
        product.family?.behavior_type === "main_package"
            ? "per pax"
            : product.family?.behavior_type === "packed_meal"
              ? "per pack"
              : "per set";
    useEffect(() => {
        if (!form.isDirty) return;
        const warn = (event: BeforeUnloadEvent) => {
            event.preventDefault();
            event.returnValue = "";
        };
        window.addEventListener("beforeunload", warn);
        return () => window.removeEventListener("beforeunload", warn);
    }, [form.isDirty]);
    return (
        <form
            className="max-w-2xl space-y-5 rounded-xl border border-stone-200 bg-white p-6"
            onSubmit={(event) => {
                event.preventDefault();
                if (variant && version)
                    form.put(
                        packagePaths.version(
                            product.id,
                            variant.id,
                            version.id,
                        ),
                    );
                else form.post(packagePaths.variants(product.id));
            }}
        >
            <p className="text-sm text-stone-600">
                Draft configuration. Save and review it before publication.
            </p>
            <Field id="name" label="Variant name" error={form.errors.name}>
                <input
                    id="name"
                    required
                    value={form.data.name}
                    onChange={(e) => form.setData("name", e.target.value)}
                    placeholder="Display name"
                />
            </Field>
            <Field
                id="description"
                label="Variant description"
                error={form.errors.description}
            >
                <textarea
                    id="description"
                    value={form.data.description}
                    onChange={(event) =>
                        form.setData("description", event.target.value)
                    }
                />
            </Field>
            <Field
                id="base_price_pesos"
                label={`Base Price (PHP, ${basis})`}
                error={form.errors.base_price_pesos}
            >
                <input
                    id="base_price_pesos"
                    required
                    inputMode="decimal"
                    pattern="[0-9]+(\.[0-9]{1,2})?"
                    value={form.data.base_price_pesos}
                    onChange={(e) =>
                        form.setData("base_price_pesos", e.target.value)
                    }
                />
            </Field>
            {product.family?.behavior_type === "packed_meal" && (
                <Field
                    id="selection_policy"
                    label="Meal selection arrangement"
                    error={form.errors.selection_policy}
                >
                    <select
                        id="selection_policy"
                        value={form.data.selection_policy}
                        onChange={(e) =>
                            form.setData(
                                "selection_policy",
                                e.target.value as "uniform" | "split",
                            )
                        }
                    >
                        <option value="uniform">Uniform configuration</option>
                        <option value="split">
                            Allow split allocations within this variant
                        </option>
                    </select>
                </Field>
            )}
            <div className="grid gap-4 sm:grid-cols-3">
                <Field
                    id="minimum_override"
                    label={`Minimum override (collection default ${defaults.minimum_quantity})`}
                    error={form.errors.minimum_override}
                >
                    <input
                        id="minimum_override"
                        type="number"
                        min="1"
                        value={form.data.minimum_override ?? ""}
                        onChange={(e) =>
                            form.setData(
                                "minimum_override",
                                e.target.value === ""
                                    ? null
                                    : Number(e.target.value),
                            )
                        }
                    />
                </Field>
                <Field
                    id="step_override"
                    label={`Increment override (collection default ${defaults.quantity_step})`}
                    error={form.errors.step_override}
                >
                    <input
                        id="step_override"
                        type="number"
                        min="1"
                        value={form.data.step_override ?? ""}
                        onChange={(e) =>
                            form.setData(
                                "step_override",
                                e.target.value === ""
                                    ? null
                                    : Number(e.target.value),
                            )
                        }
                    />
                </Field>
            </div>
            <fieldset className="space-y-2 rounded-lg border border-stone-200 p-4 text-sm">
                <legend className="font-medium">
                    Maximum purchase quantity
                </legend>
                <label className="flex gap-2">
                    <input
                        type="radio"
                        name="maximum-mode"
                        checked={!form.data.maximum_override_enabled}
                        onChange={() =>
                            form.setData({
                                ...form.data,
                                maximum_override_enabled: false,
                                maximum_override: null,
                            })
                        }
                    />
                    Use Collection Default{" "}
                    {defaults.maximum_quantity === null
                        ? "(no configured maximum)"
                        : `(${defaults.maximum_quantity})`}
                </label>
                <label className="flex gap-2">
                    <input
                        type="radio"
                        name="maximum-mode"
                        checked={
                            form.data.maximum_override_enabled &&
                            form.data.maximum_override === null
                        }
                        onChange={() =>
                            form.setData({
                                ...form.data,
                                maximum_override_enabled: true,
                                maximum_override: null,
                            })
                        }
                    />
                    No package-specific maximum
                </label>
                <label className="flex gap-2">
                    <input
                        type="radio"
                        name="maximum-mode"
                        checked={
                            form.data.maximum_override_enabled &&
                            form.data.maximum_override !== null
                        }
                        onChange={() =>
                            form.setData({
                                ...form.data,
                                maximum_override_enabled: true,
                                maximum_override:
                                    form.data.maximum_override ??
                                    defaults.minimum_quantity,
                            })
                        }
                    />
                    Set Custom Maximum
                </label>
                {form.data.maximum_override_enabled &&
                    form.data.maximum_override !== null && (
                        <Field
                            id="maximum_override"
                            label="Custom maximum"
                            error={form.errors.maximum_override}
                        >
                            <input
                                id="maximum_override"
                                type="number"
                                min="1"
                                value={form.data.maximum_override}
                                onChange={(e) =>
                                    form.setData(
                                        "maximum_override",
                                        e.target.value === ""
                                            ? null
                                            : Number(e.target.value),
                                    )
                                }
                            />
                        </Field>
                    )}
                <p className="text-xs text-stone-600">
                    No configured maximum is not a guarantee of event-date
                    capacity.
                </p>
            </fieldset>
            {Object.keys(form.errors).length > 0 && (
                <p role="alert" className="text-sm text-red-700">
                    Check the highlighted fields before saving.
                </p>
            )}
            <Button disabled={form.processing}>
                {version ? "Save draft" : "Create variant draft"}
            </Button>
        </form>
    );
}
