import type { MeasurementUnit } from "../../catalog/components/measurement-unit-form";
import { InlineMeasurementUnit } from "../../catalog/components/inline-measurement-unit";

export type ServingDefault = {
    quantity: string;
    measurement_unit_id: number | string;
    size_label: string | null;
};

export function ServingDefaultFields({
    value,
    units,
    onChange,
    onUnitCreated,
    secondary = false,
}: {
    value: ServingDefault | null;
    units: MeasurementUnit[];
    onChange: (value: ServingDefault | null) => void;
    onUnitCreated?: (unit: MeasurementUnit) => void;
    secondary?: boolean;
}) {
    const Container = secondary ? "details" : "section";
    return (
        <Container
            className="my-4 space-y-3 rounded-xl border p-4"
            aria-label="Default Serving per Food"
        >
            {secondary ? (
                <summary className="cursor-pointer font-semibold">
                    Optional food portions: Default Serving per Food
                    {value ? " (configured)" : ""}
                </summary>
            ) : (
                <h3 className="font-semibold">Default Serving per Food</h3>
            )}
            <p className="text-sm text-slate-600">
                Applies to each food without a custom serving. Changing this
                default keeps food-specific overrides unchanged. This is not the
                number of packages purchased.
            </p>
            <label className="flex items-center gap-2 text-sm">
                <input
                    type="checkbox"
                    checked={value !== null}
                    onChange={(event) =>
                        onChange(
                            event.target.checked
                                ? {
                                      quantity: "1",
                                      measurement_unit_id: "",
                                      size_label: "",
                                  }
                                : null,
                        )
                    }
                />
                Set an optional serving default
            </label>
            {value && (
                <div className="grid gap-3 sm:grid-cols-3">
                    <label className="text-sm">
                        Amount per food
                        <input
                            required
                            type="number"
                            min="0.001"
                            max="999999999.999"
                            step="0.001"
                            value={value.quantity}
                            onChange={(event) =>
                                onChange({
                                    ...value,
                                    quantity: event.target.value,
                                })
                            }
                        />
                    </label>
                    <label className="text-sm">
                        Measurement Unit
                        <select
                            required
                            value={value.measurement_unit_id}
                            onChange={(event) =>
                                onChange({
                                    ...value,
                                    measurement_unit_id: event.target.value,
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
                    <label className="text-sm">
                        Size label (optional)
                        <input
                            maxLength={100}
                            value={value.size_label ?? ""}
                            onChange={(event) =>
                                onChange({
                                    ...value,
                                    size_label: event.target.value,
                                })
                            }
                        />
                    </label>
                </div>
            )}
            {value && onUnitCreated && (
                <InlineMeasurementUnit
                    onCreated={(unit) => {
                        onUnitCreated(unit);
                        onChange({ ...value, measurement_unit_id: unit.id });
                    }}
                />
            )}
        </Container>
    );
}
