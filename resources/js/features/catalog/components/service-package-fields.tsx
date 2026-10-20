import { usePage } from "@inertiajs/react";
import { useState } from "react";
import type { MeasurementUnit } from "./measurement-unit-form";
import {
    PresentationAmountFields,
    type PresentationAmount,
} from "../../packages/components/package-presentation-fields";

export type ServicePackageInput = PresentationAmount & {
    format: "separate_servings" | "combined_contents";
};

export const initialServicePackage = (): ServicePackageInput => ({
    format: "separate_servings",
    quantity: 1,
    measurement_unit_id: null,
});

export function ServicePackageFields({
    value,
    onChange,
    locked = false,
}: {
    value: ServicePackageInput;
    onChange: (value: ServicePackageInput) => void;
    locked?: boolean;
}) {
    const props = usePage<{
        measurementUnits?: MeasurementUnit[];
        canCreateUnit?: boolean;
    }>().props;
    const [units, setUnits] = useState(props.measurementUnits ?? []);
    return (
        <section
            aria-label="Service package configuration"
            className="space-y-4 rounded-xl border p-4"
        >
            <label className="block text-sm font-medium">
                How are foods provided?
                <select
                    value={value.format}
                    disabled={locked}
                    onChange={(event) =>
                        onChange({
                            ...value,
                            format: event.target
                                .value as ServicePackageInput["format"],
                        })
                    }
                >
                    <option value="separate_servings">
                        Separate Food Servings
                    </option>
                    <option value="combined_contents">Combined Contents</option>
                </select>
            </label>
            {locked && (
                <p className="text-sm text-slate-600">
                    This service already has packages. Create another service
                    for a different food format. Container changes below apply
                    to newly created packages; existing packages retain their
                    recorded defaults.
                </p>
            )}
            <p className="text-sm text-slate-600">
                {value.format === "combined_contents"
                    ? "All foods go inside one complete pack, box or other container. Purchasing 10 units means 10 complete containers."
                    : "Each food is packed separately. Every included or selected food uses this container unless you configure an exception."}
            </p>
            <PresentationAmountFields
                value={value}
                onChange={(amount) => onChange({ ...value, ...amount })}
                units={units}
                completeContainer={value.format === "combined_contents"}
                label={
                    value.format === "combined_contents"
                        ? "One complete purchased container"
                        : "Default container per food"
                }
                onUnitCreated={
                    props.canCreateUnit
                        ? (unit) => setUnits((current) => [...current, unit])
                        : undefined
                }
            />
            {!units.length && (
                <p className="text-sm text-amber-800">
                    Create an active container unit here, or ask an
                    Administrator to add one in Food Catalog → Measurement
                    Units.
                </p>
            )}
            <p className="text-xs text-slate-500">
                Container counts do not describe food weight or portions. A
                larger food size does not automatically require another
                container.
            </p>
        </section>
    );
}
