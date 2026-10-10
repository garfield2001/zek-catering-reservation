import { useForm } from "@inertiajs/react";
import { Button } from "../../../components/ui/button";
import { Field } from "../../../components/ui/field";
import type { OrderDefault } from "../../catalog/types";
import { packagePaths } from "../paths";
import type { DraftVersion, PackageProduct, PackageVariant } from "../types";

export function VariantForm({
    product,
    variant,
    version,
    defaults,
}: {
    product: PackageProduct;
    variant: PackageVariant | null;
    version: DraftVersion | null;
    defaults: OrderDefault;
}) {
    const form = useForm({
        code: variant?.code ?? "",
        name: variant?.name ?? "",
        description: variant?.description ?? "",
        sort_order: variant?.sort_order ?? 0,
        active: variant?.active ?? true,
        base_price_minor: version?.base_price_minor ?? "0",
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
                Draft only. No customer can book or price this package until a
                later publication phase.
            </p>
            <Field id="code" label="Variant code" error={form.errors.code}>
                <input
                    id="code"
                    required
                    readOnly={!!variant}
                    value={form.data.code}
                    onChange={(e) => form.setData("code", e.target.value)}
                    placeholder="variant_code"
                />
            </Field>
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
                id="sort_order"
                label="Display order"
                error={form.errors.sort_order}
            >
                <input
                    id="sort_order"
                    type="number"
                    value={form.data.sort_order}
                    onChange={(event) =>
                        form.setData("sort_order", Number(event.target.value))
                    }
                />
            </Field>
            <Field
                id="base_price_minor"
                label={`Base price in centavos (${basis})`}
                error={form.errors.base_price_minor}
            >
                <input
                    id="base_price_minor"
                    required
                    inputMode="numeric"
                    pattern="[0-9]+"
                    value={form.data.base_price_minor}
                    onChange={(e) =>
                        form.setData("base_price_minor", e.target.value)
                    }
                />
            </Field>
            <Field
                id="selection_policy"
                label="Selection policy"
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
                    {product.family?.behavior_type === "packed_meal" && (
                        <option value="split">
                            Allow split allocations within this variant
                        </option>
                    )}
                </select>
            </Field>
            <div className="grid gap-4 sm:grid-cols-3">
                <Field
                    id="minimum_override"
                    label={`Minimum override (family ${defaults.minimum_quantity})`}
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
                    label={`Increment override (family ${defaults.quantity_step})`}
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
                <Field
                    id="maximum_override"
                    label="Maximum override"
                    error={form.errors.maximum_override}
                >
                    <input
                        id="maximum_override"
                        type="number"
                        min="1"
                        disabled={!form.data.maximum_override_enabled}
                        value={form.data.maximum_override ?? ""}
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
            </div>
            <label className="flex items-center gap-2 text-sm">
                <input
                    type="checkbox"
                    checked={form.data.maximum_override_enabled}
                    onChange={(e) =>
                        form.setData({
                            ...form.data,
                            maximum_override_enabled: e.target.checked,
                            maximum_override: e.target.checked
                                ? form.data.maximum_override
                                : null,
                        })
                    }
                />
                Override inherited maximum (leave blank for unbounded)
            </label>
            <label className="flex items-center gap-2 text-sm">
                <input
                    type="checkbox"
                    checked={form.data.active}
                    onChange={(e) => form.setData("active", e.target.checked)}
                />
                Active draft owner
            </label>
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
