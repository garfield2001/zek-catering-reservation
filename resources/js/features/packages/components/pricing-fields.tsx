import type { QuantitySource } from "./pricing-form";
import { Field } from "../../../components/ui/field";
export type PricingData = {
    base_price_pesos: string;
    price_basis: "per_pax" | "per_pack" | "per_set";
    selection_policy: "uniform" | "split";
    minimum_quantity: string;
    quantity_step: string;
    maximum_quantity: string;
    estimated_capacity: string;
};
export function PricingFields({
    data,
    onChange,
    main,
    configurable: _configurable,
    purchaseLabel,
    legacySplit = false,
    disabled,
    sources = [],
}: {
    data: PricingData;
    onChange: (values: Partial<PricingData>) => void;
    main: boolean;
    configurable: boolean;
    purchaseLabel?: string;
    legacySplit?: boolean;
    disabled: boolean;
    sources?: QuantitySource[];
}) {
    const split = data.price_basis === "per_pack";
    const unit = main
        ? "guests"
        : purchaseLabel
          ? `${purchaseLabel} units`
          : split
            ? "packs"
            : "packages";
    const form = {
        data,
        errors: {} as Record<string, string>,
        setData: <K extends keyof PricingData>(key: K, value: PricingData[K]) =>
            onChange({ [key]: value }),
    };
    return (
        <fieldset disabled={disabled} className="space-y-5">
            {disabled && (
                <p className="text-sm text-amber-800">
                    These saved terms are read-only. Ask an Administrator for
                    package pricing permission to change them.
                </p>
            )}
            {!!sources.length && (
                <Field id="copy-quantity" label="Copy quantity settings from">
                    <select
                        id="copy-quantity"
                        value=""
                        onChange={(e) => {
                            const source = sources.find(
                                (s) => s.id === Number(e.target.value),
                            );
                            if (source)
                                onChange({
                                    minimum_quantity: String(
                                        source.minimum_quantity,
                                    ),
                                    quantity_step: String(source.quantity_step),
                                    maximum_quantity:
                                        source.maximum_quantity?.toString() ??
                                        "",
                                });
                        }}
                    >
                        <option value="">Choose a package (optional)</option>
                        {sources.map((source) => (
                            <option key={source.id} value={source.id}>
                                {source.product.name}
                            </option>
                        ))}
                    </select>
                    <p className="text-xs text-stone-500">
                        Copies values only. Packages remain independently
                        editable.
                    </p>
                </Field>
            )}
            {_configurable && legacySplit && (
                <details>
                    <summary className="cursor-pointer text-sm text-emerald-800">
                        Packed meal settings (optional)
                    </summary>
                    <label className="mt-3 flex gap-2">
                        <input
                            type="checkbox"
                            checked={split}
                            onChange={(e) =>
                                onChange({
                                    price_basis: e.target.checked
                                        ? "per_pack"
                                        : "per_set",
                                    selection_policy: "uniform",
                                })
                            }
                        />
                        Sell each purchased unit as a packed meal
                    </label>
                    <p className="mt-1 text-sm text-slate-500">
                        Enables uniform or split meal selections. Purchase
                        minimums still apply to the total order.
                    </p>
                </details>
            )}
            <Field
                id="package-price"
                label={
                    main
                        ? "Price per pax (PHP)"
                        : purchaseLabel
                          ? `Price per ${purchaseLabel} (PHP)`
                          : split
                            ? "Price per pack (PHP)"
                            : "Price per package (PHP)"
                }
                error={form.errors.base_price_pesos}
            >
                <input
                    id="package-price"
                    inputMode="decimal"
                    required
                    value={form.data.base_price_pesos}
                    onChange={(e) =>
                        form.setData("base_price_pesos", e.target.value)
                    }
                />
            </Field>
            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    id="package-minimum"
                    label={`Minimum ${unit}`}
                    error={form.errors.minimum_quantity}
                >
                    <input
                        id="package-minimum"
                        type="number"
                        min="1"
                        required
                        value={form.data.minimum_quantity}
                        onChange={(e) =>
                            form.setData("minimum_quantity", e.target.value)
                        }
                    />
                </Field>
                <Field
                    id="package-step"
                    label="Quantity increment"
                    error={form.errors.quantity_step}
                >
                    <input
                        id="package-step"
                        type="number"
                        min="1"
                        required
                        value={form.data.quantity_step}
                        onChange={(e) =>
                            form.setData("quantity_step", e.target.value)
                        }
                    />
                </Field>
            </div>
            <Field
                id="package-maximum"
                label={`Maximum ${unit} (optional)`}
                error={form.errors.maximum_quantity}
            >
                <input
                    id="package-maximum"
                    type="number"
                    min="1"
                    value={form.data.maximum_quantity}
                    onChange={(e) =>
                        form.setData("maximum_quantity", e.target.value)
                    }
                />
            </Field>
            {!main && (
                <Field
                    id="capacity"
                    label={`Estimated people served per ${purchaseLabel ?? (split ? "pack" : "package")} (optional)`}
                    error={form.errors.estimated_capacity}
                >
                    <input
                        id="capacity"
                        type="number"
                        min="1"
                        value={form.data.estimated_capacity}
                        onChange={(e) =>
                            form.setData("estimated_capacity", e.target.value)
                        }
                    />
                    <p className="mt-1 text-xs text-slate-500">
                        An estimate, not a guest requirement or kilogram
                        conversion.
                    </p>
                </Field>
            )}
            {split && legacySplit && (
                <Field
                    id="package-selection"
                    label="Meal selections"
                    error={form.errors.selection_policy}
                >
                    <select
                        id="package-selection"
                        value={form.data.selection_policy}
                        onChange={(e) =>
                            form.setData(
                                "selection_policy",
                                e.target.value as "uniform" | "split",
                            )
                        }
                    >
                        <option value="uniform">Same meal for everyone</option>
                        <option value="split">
                            Allow different meal groups
                        </option>
                    </select>
                </Field>
            )}
        </fieldset>
    );
}
