import { Field } from "../../../components/ui/field";
export type FoodDetail = {
    is_fixed: boolean;
    included_quantity: string;
    included_unit: string;
    price_adjustment_pesos: string;
};

export function MenuFoodDetails({
    food,
    value,
    onChange,
    bundle,
    packed,
    canPrice,
    showFixed = true,
}: {
    food: { id: number; name: string };
    value: FoodDetail;
    onChange: (values: Partial<FoodDetail>) => void;
    bundle: boolean;
    packed: boolean;
    canPrice: boolean;
    showFixed?: boolean;
}) {
    return (
        <details className="ml-6 mt-2 text-sm">
            <summary className="cursor-pointer text-emerald-800">
                Quantity & preparation details for {food.name}
            </summary>
            <div className="mt-3 space-y-3 rounded-lg bg-slate-50 p-3">
                {!bundle && showFixed && (
                    <label className="flex gap-2">
                        <input
                            type="checkbox"
                            checked={value.is_fixed}
                            onChange={(e) =>
                                onChange({ is_fixed: e.target.checked })
                            }
                        />
                        Always include {food.name}
                    </label>
                )}
                <div className="grid grid-cols-2 gap-3">
                    <Field
                        id={`food-quantity-${food.id}`}
                        label={`Included quantity of ${food.name} (optional)`}
                    >
                        <input
                            id={`food-quantity-${food.id}`}
                            inputMode="decimal"
                            value={value.included_quantity}
                            onChange={(e) =>
                                onChange({ included_quantity: e.target.value })
                            }
                        />
                    </Field>
                    <Field
                        id={`food-unit-${food.id}`}
                        label={`Measurement for ${food.name}`}
                    >
                        <select
                            id={`food-unit-${food.id}`}
                            value={value.included_unit}
                            onChange={(e) =>
                                onChange({ included_unit: e.target.value })
                            }
                        >
                            <option value="">No measurement</option>
                            {[
                                "kg",
                                "tray",
                                "portion",
                                "piece",
                                "bottle",
                                "pack",
                            ].map((unit) => (
                                <option key={unit}>{unit}</option>
                            ))}
                        </select>
                    </Field>
                </div>
                <p className="text-xs text-slate-500">
                    Per purchased {packed ? "pack" : "package"}. Leave blank
                    when a measurement is not meaningful.
                </p>
                {!bundle && canPrice && (
                    <Field
                        id={`food-price-${food.id}`}
                        label={`Extra price for ${food.name} (PHP)`}
                    >
                        <input
                            id={`food-price-${food.id}`}
                            inputMode="decimal"
                            value={value.price_adjustment_pesos}
                            onChange={(e) =>
                                onChange({
                                    price_adjustment_pesos: e.target.value,
                                })
                            }
                        />
                    </Field>
                )}
            </div>
        </details>
    );
}
