import { useState } from "react";
import { ConfirmDialog } from "../../../components/ui/confirm-dialog";
import type { MeasurementUnit } from "../../catalog/components/measurement-unit-form";
import { MenuFoodDetails, type FoodDetail } from "./menu-food-details";
import {
    FoodSizeFields,
    type FoodSizeInput,
    type FoodSizes,
} from "./food-size-fields";
import type { ServingDefault } from "./serving-default-fields";

/** One food's fulfillment facts; purchase quantities remain in Pricing & Quantity. */
export function FoodConfiguration({
    food,
    detail,
    sizes,
    servingDefault,
    units,
    fixed,
    bundle,
    packed,
    canPrice,
    onChange,
    onUnitCreated,
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
}) {
    const [pendingSizes, setPendingSizes] = useState<FoodSizes | null>(null);
    const defaultSize = sizes?.choices.find((choice) => choice.is_default);
    const custom = Boolean(
        sizes || detail.included_quantity || detail.included_unit,
    );
    const unitId =
        sizes?.measurement_unit_id ?? servingDefault?.measurement_unit_id;
    const unit = units.find((item) => item.id === Number(unitId));
    const serving = sizes
        ? `${defaultSize?.quantity || "Amount needed"} ${defaultSize?.size_label ?? ""} ${unit?.abbreviation ?? "Unit needed"}`
        : custom
          ? `${detail.included_quantity} ${detail.included_unit}`
          : servingDefault
            ? `${servingDefault.quantity} ${servingDefault.size_label ?? ""} ${unit?.abbreviation ?? "Unit needed"}`
            : "No serving measurement";
    const clearMeasurement = (): FoodDetail => ({
        ...detail,
        included_quantity: "",
        included_unit: "",
    });
    return (
        <section
            aria-label={`Food Configuration for ${food.name}`}
            className="mt-3 space-y-3 border-t pt-3"
        >
            <p className="text-xs text-slate-600">
                {fixed ? "Fixed inclusion" : "Customer choice"} ·{" "}
                {custom ? "Custom" : "Default"} — {serving}
                {sizes && sizes.choices.length > 1 && " · upgrades available"}
            </p>
            {custom && (
                <>
                    <button
                        type="button"
                        className="text-xs text-emerald-800 underline"
                        disabled={Boolean(sizes) && !canPrice}
                        onClick={() => onChange(clearMeasurement(), undefined)}
                    >
                        Return {food.name} to package default
                    </button>
                    {sizes && !canPrice && (
                        <p className="text-xs">
                            Ask an Administrator for pricing permission to
                            remove size choices.
                        </p>
                    )}
                    {sizes && (
                        <p className="text-xs text-slate-500">
                            Returning to the default removes this food's size
                            choices and upgrade prices from the unsaved Draft.
                        </p>
                    )}
                </>
            )}
            {!sizes && (
                <MenuFoodDetails
                    food={food}
                    value={detail}
                    onChange={(values) => onChange({ ...detail, ...values })}
                    bundle={bundle}
                    packed={packed}
                    canPrice={canPrice}
                    showFixed={false}
                />
            )}
            <FoodSizeFields
                foods={[food]}
                units={units}
                value={sizes ? { [food.id]: sizes } : {}}
                canPrice={canPrice}
                onUnitCreated={onUnitCreated}
                onChange={(value) => {
                    if (
                        value[food.id] &&
                        !sizes &&
                        (custom || Number(detail.price_adjustment_pesos) !== 0)
                    ) {
                        setPendingSizes(value);
                    } else onChange(detail, value[food.id]);
                }}
            />
            <ConfirmDialog
                open={pendingSizes !== null}
                onClose={() => setPendingSizes(null)}
                title="Replace food configuration?"
                description="Size choices replace this food's separate included measurement and extra price. Configure the included size and upgrade prices together. Your other foods and package default stay unchanged."
                confirmLabel="Use Size Choices"
                onConfirm={() => {
                    onChange(
                        { ...clearMeasurement(), price_adjustment_pesos: "0" },
                        pendingSizes?.[food.id],
                    );
                    setPendingSizes(null);
                }}
            />
        </section>
    );
}
