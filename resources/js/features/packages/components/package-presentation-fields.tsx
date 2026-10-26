import type { MeasurementUnit } from "../../catalog/components/measurement-unit-form";
import { InlineMeasurementUnit } from "../../catalog/components/inline-measurement-unit";
import { useState } from "react";

export type PresentationAmount = {
    quantity: number | string | null;
    measurement_unit_id: number | string | null;
};
export type PackagePresentationInput = PresentationAmount & {
    format: "separate_servings" | "combined_contents";
    food_overrides: Record<number, PresentationAmount>;
};
export const separatePresentation = (): PackagePresentationInput => ({
    format: "separate_servings",
    quantity: null,
    measurement_unit_id: null,
    food_overrides: {},
});

export function PresentationAmountFields({
    value,
    onChange,
    units,
    label,
    onUnitCreated,
    onUnitsChanged,
    completeContainer = false,
    quantityLabel = "Container count",
    unitLabel = "Presentation unit",
    emptyUnitLabel = "Not specified",
}: {
    value: PresentationAmount;
    onChange: (value: PresentationAmount) => void;
    units: MeasurementUnit[];
    label: string;
    onUnitCreated?: (unit: MeasurementUnit) => void;
    onUnitsChanged?: (units: MeasurementUnit[]) => void;
    completeContainer?: boolean;
    quantityLabel?: string;
    unitLabel?: string;
    emptyUnitLabel?: string;
}) {
    return (
        <fieldset className="grid gap-3 sm:grid-cols-2">
            <legend className="mb-2 text-sm font-medium">{label}</legend>
            <label className="text-sm">
                {quantityLabel}
                <input
                    type="number"
                    min="1"
                    max={completeContainer ? "1" : "2147483647"}
                    required={
                        completeContainer || Boolean(value.measurement_unit_id)
                    }
                    value={value.quantity ?? ""}
                    onChange={(e) =>
                        onChange({ ...value, quantity: e.target.value || null })
                    }
                />
            </label>
            <label className="text-sm">
                {unitLabel}
                <select
                    required={completeContainer || Boolean(value.quantity)}
                    value={value.measurement_unit_id ?? ""}
                    onChange={(e) =>
                        onChange({
                            ...value,
                            measurement_unit_id: e.target.value || null,
                        })
                    }
                >
                    <option value="">{emptyUnitLabel}</option>
                    {units.map((unit) => (
                        <option key={unit.id} value={unit.id}>
                            {unit.name}
                        </option>
                    ))}
                </select>
            </label>
            {onUnitCreated && (
                <InlineMeasurementUnit
                    onCreated={(unit) => {
                        onUnitCreated(unit);
                        onChange({ ...value, measurement_unit_id: unit.id });
                    }}
                    onUnitsChanged={onUnitsChanged}
                />
            )}
        </fieldset>
    );
}

export function PackagePresentationFields({
    value,
    onChange,
    units,
    onUnitCreated,
    onUnitsChanged,
    legacySplit,
}: {
    value: PackagePresentationInput | null;
    onChange: (value: PackagePresentationInput | null) => void;
    units: MeasurementUnit[];
    onUnitCreated?: (unit: MeasurementUnit) => void;
    onUnitsChanged?: (units: MeasurementUnit[]) => void;
    legacySplit: boolean;
}) {
    if (legacySplit)
        return (
            <p className="rounded bg-amber-50 p-3 text-sm">
                This legacy package allows different meal groups. Its
                configuration is preserved. Choose uniform selections in Pricing
                & Quantity before configuring Package Format.
            </p>
        );
    return (
        <section
            className="space-y-3 rounded-xl border p-4"
            aria-label="Package Format"
        >
            <h3 className="font-semibold">Package Format</h3>
            <label className="block text-sm">
                What does the customer receive when purchasing one unit of this
                package?
                <select
                    value={value?.format ?? ""}
                    onChange={(e) =>
                        onChange({
                            ...(value ?? separatePresentation()),
                            format: e.target
                                .value as PackagePresentationInput["format"],
                        })
                    }
                >
                    {!value && (
                        <option value="">
                            Legacy format not recorded — choose explicitly
                        </option>
                    )}
                    <option value="separate_servings">
                        Separate Food Servings
                    </option>
                    <option value="combined_contents">Combined Contents</option>
                </select>
            </label>
            {value && (
                <>
                    <p className="text-sm text-slate-600">
                        {value.format === "combined_contents"
                            ? "All included and selected foods are combined inside each complete purchased pack, box or other container. Configure one container below. Food portions remain optional."
                            : "Each included or selected food is provided separately, according to its serving quantity and presentation. A common presentation is optional; exceptions can be configured in Menu."}
                    </p>
                    <PresentationAmountFields
                        completeContainer={value.format === "combined_contents"}
                        value={value}
                        onChange={(amount) => onChange({ ...value, ...amount })}
                        units={units}
                        onUnitCreated={onUnitCreated}
                        onUnitsChanged={onUnitsChanged}
                        label={
                            value.format === "combined_contents"
                                ? "One complete purchased unit"
                                : "Default presentation per food (optional)"
                        }
                    />
                    <p className="text-xs text-slate-500">
                        Presentation is independent of food weight, portion
                        size, price and the number of distinct foods. A weight
                        upgrade does not change container count.
                    </p>
                    {value.format === "combined_contents" &&
                        Object.keys(value.food_overrides).length > 0 && (
                            <p role="alert" className="text-sm text-amber-800">
                                Separate-food presentation overrides still
                                exist. Return to Separate Food Servings and
                                remove each exception before saving Combined
                                Contents. Nothing has been discarded.
                            </p>
                        )}
                </>
            )}
        </section>
    );
}

export function purchasedUnit(
    value: PackagePresentationInput | null,
    units: MeasurementUnit[],
): string {
    if (!value) return "complete purchased unit";
    return value.format === "separate_servings"
        ? "complete package set"
        : `complete ${units.find((unit) => unit.id === Number(value.measurement_unit_id))?.name ?? "container (choose presentation)"}`;
}

export function FoodPresentationFields({
    food,
    value,
    units,
    onChange,
    onUnitCreated,
    onUnitsChanged,
}: {
    food: { id: number; name: string };
    value: PackagePresentationInput | null;
    units: MeasurementUnit[];
    onChange: (value: PackagePresentationInput) => void;
    onUnitCreated?: (unit: MeasurementUnit) => void;
    onUnitsChanged?: (units: MeasurementUnit[]) => void;
}) {
    const [editingOverride, setEditingOverride] = useState(false);
    if (value?.format !== "separate_servings") return null;
    const defaultAmount: PresentationAmount = {
        quantity: value.quantity ?? 1,
        measurement_unit_id: value.measurement_unit_id ?? null,
    };
    const equivalent = (amount: PresentationAmount) =>
        String(amount.quantity) === String(defaultAmount.quantity) &&
        String(amount.measurement_unit_id ?? "") ===
            String(defaultAmount.measurement_unit_id ?? "");
    const update = (amount?: PresentationAmount) => {
        const overrides = { ...value.food_overrides };
        if (amount && !equivalent(amount)) overrides[food.id] = amount;
        else delete overrides[food.id];
        onChange({ ...value, food_overrides: overrides });
    };
    const override = value.food_overrides[food.id];
    return (
        <section
            aria-label={`Different packaging for ${food.name}`}
            className="mt-3 rounded-lg bg-slate-50 p-3 text-sm"
        >
            <label className="my-2 flex gap-2">
                <input
                    type="checkbox"
                    checked={Boolean(override) || editingOverride}
                    onChange={(event) => {
                        setEditingOverride(event.target.checked);
                        if (!event.target.checked) update(undefined);
                    }}
                />
                Use different packaging for this food
            </label>
            {(override || editingOverride) && (
                <>
                    <PresentationAmountFields
                        label="Different packaging"
                        value={override ?? defaultAmount}
                        units={units}
                        onChange={(amount) => {
                            setEditingOverride(!equivalent(amount));
                            update(amount);
                        }}
                        onUnitCreated={onUnitCreated}
                        onUnitsChanged={onUnitsChanged}
                    />
                    {!override && (
                        <p className="mt-2 text-xs text-slate-600">
                            This food already uses {defaultAmount.quantity}{" "}
                            {units
                                .find(
                                    (unit) =>
                                        unit.id ===
                                        Number(
                                            defaultAmount.measurement_unit_id,
                                        ),
                                )
                                ?.name?.toLowerCase() ?? "default container"}
                            . Choose a different quantity or container to save
                            an exception.
                        </p>
                    )}
                </>
            )}
        </section>
    );
}
