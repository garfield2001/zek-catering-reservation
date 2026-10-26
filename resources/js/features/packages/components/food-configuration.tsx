import { useState, type ReactNode } from "react";
import type { PackagePresentationInput } from "./package-presentation-fields";
import { ConfirmDialog } from "../../../components/ui/confirm-dialog";
import type { MeasurementUnit } from "../../catalog/components/measurement-unit-form";
import { InlineMeasurementUnit } from "../../catalog/components/inline-measurement-unit";
import type { FoodDetail } from "./menu-food-details";
import { FoodSizeFields, type FoodSizeInput } from "./food-size-fields";
import type { ServingDefault } from "./serving-default-fields";

type ServingMode = "standard" | "fixed" | "sizes";

const emptySizeChoices = (): FoodSizeInput => ({
    measurement_unit_id: "",
    choices: [{ quantity: "", extra_price_pesos: "0", is_default: true }],
});

/** One optional business-facing configuration entry point for an included food. */
export function FoodConfiguration({
    food,
    detail,
    sizes,
    servingDefault,
    units,
    fixed,
    bundle,
    canPrice,
    onChange,
    onUnitCreated,
    onUnitsChanged,
    presentation,
    children,
}: {
    food: { id: number; name: string };
    detail: FoodDetail;
    sizes?: FoodSizeInput;
    servingDefault: ServingDefault | null;
    units: MeasurementUnit[];
    fixed: boolean;
    bundle: boolean;
    packed: boolean;
    canPrice: boolean;
    onChange: (detail: FoodDetail, sizes?: FoodSizeInput) => void;
    onUnitCreated?: (unit: MeasurementUnit) => void;
    onUnitsChanged?: (units: MeasurementUnit[]) => void;
    presentation?: PackagePresentationInput | null;
    children?: ReactNode;
}) {
    const [pendingMode, setPendingMode] = useState<ServingMode | null>(null);
    const container =
        presentation?.format === "separate_servings"
            ? (presentation.food_overrides[food.id] ?? presentation)
            : null;
    const selectedUnitId =
        detail.included_unit_id ||
        units.find((unit) => unit.abbreviation === detail.included_unit)?.id ||
        "";
    const selectedUnit = units.find(
        (unit) => unit.id === Number(selectedUnitId),
    );
    const mode: ServingMode = sizes
        ? "sizes"
        : detail.included_quantity
          ? "fixed"
          : "standard";
    const containerLabel = container?.measurement_unit_id
        ? `${container.quantity} ${units.find((unit) => unit.id === Number(container.measurement_unit_id))?.name ?? "container"}`
        : presentation?.format === "combined_contents"
          ? "Inside the complete purchased container"
          : "Packaging not recorded";
    const sizeUnit = units.find(
        (unit) => unit.id === Number(sizes?.measurement_unit_id),
    );
    const defaultSize = sizes?.choices.find((choice) => choice.is_default);
    const serving = sizes
        ? `${defaultSize?.quantity || "Amount needed"} ${defaultSize?.size_label ?? ""} ${sizeUnit?.abbreviation ?? "unit needed"}${sizes.choices.length > 1 ? " available" : ""}`
        : detail.included_quantity
          ? `${detail.included_quantity} ${selectedUnit?.abbreviation ?? detail.included_unit}`
          : servingDefault
            ? `${servingDefault.quantity} ${servingDefault.size_label ?? ""} ${units.find((unit) => unit.id === Number(servingDefault.measurement_unit_id))?.abbreviation ?? "unit needed"}`
            : "Standard serving";
    const clearPortion = (): FoodDetail => ({
        ...detail,
        included_quantity: "",
        included_unit: "",
        included_unit_id: "",
    });
    const applyMode = (next: ServingMode) => {
        if (next === "sizes") {
            onChange(
                { ...clearPortion(), price_adjustment_pesos: "0" },
                emptySizeChoices(),
            );
            return;
        }
        if (next === "fixed") {
            onChange(
                {
                    ...clearPortion(),
                    included_quantity: "1",
                    included_unit_id: "",
                },
                undefined,
            );
            return;
        }
        onChange(clearPortion(), undefined);
    };
    const requestMode = (next: ServingMode) => {
        if (next === mode) return;
        if (
            mode === "sizes" ||
            (next === "sizes" && Number(detail.price_adjustment_pesos) > 0)
        ) {
            setPendingMode(next);
            return;
        }
        applyMode(next);
    };
    return (
        <section
            aria-label={`Food Options for ${food.name}`}
            className="mt-3 border-t pt-3"
        >
            <p className="text-xs text-slate-600">
                {serving} · {containerLabel} ·{" "}
                {Number(detail.price_adjustment_pesos) > 0
                    ? `+ PHP ${detail.price_adjustment_pesos} when selected`
                    : "No extra charge"}
                . {fixed ? "Included food" : "Customer choice"}
            </p>
            <details className="mt-3 space-y-4 text-sm">
                <summary className="cursor-pointer font-medium text-emerald-800">
                    Food Options
                </summary>
                <p className="text-xs text-slate-500">
                    Use the service defaults for ordinary foods. Portions and
                    packaging are separate: changing a food's weight does not
                    change its container.
                </p>
                <fieldset className="space-y-2">
                    <legend className="font-medium">
                        How much is included?
                    </legend>
                    <label className="flex gap-2">
                        <input
                            type="radio"
                            aria-label="Use standard serving"
                            name={`serving-mode-${food.id}`}
                            checked={mode === "standard"}
                            onChange={() => requestMode("standard")}
                        />
                        <span>
                            Use standard serving
                            <span className="block text-xs text-slate-500">
                                Use the package's food portion, when set, and
                                the service's default packaging.
                            </span>
                        </span>
                    </label>
                    <label className="flex gap-2">
                        <input
                            type="radio"
                            aria-label="Set a fixed portion"
                            name={`serving-mode-${food.id}`}
                            checked={mode === "fixed"}
                            onChange={() => requestMode("fixed")}
                        />
                        <span>
                            Set a fixed portion
                            <span className="block text-xs text-slate-500">
                                Specify how much of this food is included.
                            </span>
                        </span>
                    </label>
                    <label className="flex gap-2">
                        <input
                            type="radio"
                            aria-label="Offer size choices"
                            name={`serving-mode-${food.id}`}
                            checked={mode === "sizes"}
                            disabled={!canPrice}
                            onChange={() => requestMode("sizes")}
                        />
                        <span>
                            Offer size choices
                            <span className="block text-xs text-slate-500">
                                Let customers choose an included size or paid
                                upgrade. Size choices replace a separate food
                                charge.
                            </span>
                        </span>
                    </label>
                </fieldset>
                {mode === "fixed" && (
                    <div className="grid gap-3 sm:grid-cols-2">
                        <label>
                            Amount
                            <input
                                required
                                type="number"
                                min="0.001"
                                max="999999999.999"
                                step="0.001"
                                value={detail.included_quantity}
                                onChange={(event) =>
                                    onChange({
                                        ...detail,
                                        included_quantity: event.target.value,
                                    })
                                }
                            />
                        </label>
                        <label>
                            Measurement Unit
                            <select
                                required
                                value={selectedUnitId}
                                onChange={(event) =>
                                    onChange({
                                        ...detail,
                                        included_unit_id: event.target.value,
                                        included_unit: "",
                                    })
                                }
                            >
                                <option value="">Choose a unit</option>
                                {units.map((unit) => (
                                    <option key={unit.id} value={unit.id}>
                                        {unit.name} ({unit.abbreviation})
                                    </option>
                                ))}
                            </select>
                        </label>
                        {onUnitCreated && (
                            <div className="sm:col-span-2">
                                <InlineMeasurementUnit
                                    onCreated={(unit) => {
                                        onUnitCreated(unit);
                                        onChange({
                                            ...detail,
                                            included_unit_id: unit.id,
                                            included_unit: "",
                                        });
                                    }}
                                    onUnitsChanged={onUnitsChanged}
                                />
                            </div>
                        )}
                    </div>
                )}
                {mode === "sizes" && (
                    <FoodSizeFields
                        foods={[food]}
                        units={units}
                        value={{ [food.id]: sizes ?? emptySizeChoices() }}
                        canPrice={canPrice}
                        onUnitCreated={onUnitCreated}
                        onUnitsChanged={onUnitsChanged}
                        onChange={(value) => onChange(detail, value[food.id])}
                        selectionOnly
                    />
                )}
                {mode !== "sizes" && !bundle && (
                    <label className="block rounded-lg bg-slate-50 p-3">
                        <span className="flex items-center gap-2 font-medium">
                            <input
                                type="checkbox"
                                checked={
                                    Number(detail.price_adjustment_pesos) > 0
                                }
                                disabled={!canPrice}
                                onChange={(event) =>
                                    onChange({
                                        ...detail,
                                        price_adjustment_pesos: event.target
                                            .checked
                                            ? detail.price_adjustment_pesos ===
                                              "0"
                                                ? "1"
                                                : detail.price_adjustment_pesos
                                            : "0",
                                    })
                                }
                            />
                            Charge extra when this food is selected
                        </span>
                        {Number(detail.price_adjustment_pesos) > 0 && (
                            <label className="mt-3 block">
                                Additional charge (PHP)
                                <input
                                    required
                                    inputMode="decimal"
                                    disabled={!canPrice}
                                    value={detail.price_adjustment_pesos}
                                    onChange={(event) =>
                                        onChange({
                                            ...detail,
                                            price_adjustment_pesos:
                                                event.target.value,
                                        })
                                    }
                                />
                            </label>
                        )}
                    </label>
                )}
                {children}
            </details>
            <ConfirmDialog
                open={pendingMode !== null}
                onClose={() => setPendingMode(null)}
                title="Replace food options?"
                description={
                    pendingMode === "sizes"
                        ? "Size choices replace this food's separate portion and extra charge. Your other package settings remain unchanged."
                        : "This removes the food's size choices and upgrade prices from the unsaved Draft. Your other package settings remain unchanged."
                }
                confirmLabel={
                    pendingMode === "sizes"
                        ? "Use Size Choices"
                        : "Change Serving"
                }
                onConfirm={() => {
                    if (pendingMode) applyMode(pendingMode);
                    setPendingMode(null);
                }}
            />
        </section>
    );
}
