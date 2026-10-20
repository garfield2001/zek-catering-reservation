import { Link } from "@inertiajs/react";
import type { MeasurementUnit } from "../../catalog/components/measurement-unit-form";
import { InlineMeasurementUnit } from "../../catalog/components/inline-measurement-unit";

export type FoodSizeChoice = {
    quantity: string;
    size_label?: string | null;
    extra_price_pesos: string;
    is_default: boolean;
};
export type FoodSizeInput = {
    measurement_unit_id: number | string;
    choices: FoodSizeChoice[];
};
export type FoodSizes = Record<number, FoodSizeInput>;

export function FoodSizeFields({
    foods,
    units,
    value,
    onChange,
    canPrice,
    onUnitCreated,
    selectionOnly = false,
}: {
    foods: { id: number; name: string }[];
    units: MeasurementUnit[];
    value: FoodSizes;
    onChange: (value: FoodSizes) => void;
    canPrice: boolean;
    onUnitCreated?: (unit: MeasurementUnit) => void;
    selectionOnly?: boolean;
}) {
    const update = (id: number, group: FoodSizeInput) =>
        onChange({ ...value, [id]: group });
    return (
        <section className="space-y-3" aria-label="Food size choices">
            <h3 className="font-semibold">Size choices</h3>
            <p className="text-sm text-slate-600">
                Each food still counts once. Extra prices apply to each
                purchased package. The included default costs no extra.
            </p>
            {!units.length && (
                <p className="rounded-lg bg-amber-50 p-3 text-sm">
                    Create a Measurement Unit in{" "}
                    <Link
                        href="/admin/catalog/measurement-units"
                        className="underline"
                    >
                        Catalog Settings
                    </Link>{" "}
                    before offering size choices. Save your Draft before
                    leaving.
                </p>
            )}
            {!canPrice && (
                <p className="text-sm text-amber-800">
                    Pricing permission is required to change size choices. Ask
                    an Administrator for access.
                </p>
            )}
            {foods.map((food) => {
                const group = value[food.id];
                const unit = units.find(
                    (item) => item.id === Number(group?.measurement_unit_id),
                );
                return (
                    <fieldset
                        key={food.id}
                        disabled={!canPrice}
                        className="rounded-xl border bg-white p-4"
                    >
                        {!selectionOnly && (
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={!!group}
                                    onChange={(event) => {
                                        if (event.target.checked)
                                            update(food.id, {
                                                measurement_unit_id: "",
                                                choices: [
                                                    {
                                                        quantity: "",
                                                        extra_price_pesos: "0",
                                                        is_default: true,
                                                    },
                                                ],
                                            });
                                        else
                                            onChange(
                                                Object.fromEntries(
                                                    Object.entries(
                                                        value,
                                                    ).filter(
                                                        ([id]) =>
                                                            Number(id) !==
                                                            food.id,
                                                    ),
                                                ),
                                            );
                                    }}
                                />
                                Offer size choices
                            </label>
                        )}
                        {group && (
                            <div className="mt-4 space-y-3">
                                <label className="block text-sm">
                                    Measurement Unit for {food.name}
                                    <select
                                        required
                                        value={group.measurement_unit_id}
                                        onChange={(event) =>
                                            update(food.id, {
                                                ...group,
                                                measurement_unit_id:
                                                    event.target.value,
                                            })
                                        }
                                    >
                                        <option value="">Choose a unit</option>
                                        {units.map((item) => (
                                            <option
                                                key={item.id}
                                                value={item.id}
                                            >
                                                {item.name} ({item.abbreviation}
                                                )
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                {onUnitCreated && (
                                    <InlineMeasurementUnit
                                        onCreated={(unit) => {
                                            onUnitCreated(unit);
                                            update(food.id, {
                                                ...group,
                                                measurement_unit_id: unit.id,
                                            });
                                        }}
                                    />
                                )}
                                {group.choices.map((choice, index) => (
                                    <div
                                        key={index}
                                        className="grid gap-2 rounded-lg bg-slate-50 p-3 sm:grid-cols-2"
                                    >
                                        <label className="text-xs">
                                            Size label {index + 1} (optional)
                                            <input
                                                maxLength={100}
                                                value={choice.size_label ?? ""}
                                                onChange={(event) =>
                                                    update(food.id, {
                                                        ...group,
                                                        choices:
                                                            group.choices.map(
                                                                (row, i) =>
                                                                    i === index
                                                                        ? {
                                                                              ...row,
                                                                              size_label:
                                                                                  event
                                                                                      .target
                                                                                      .value,
                                                                          }
                                                                        : row,
                                                            ),
                                                    })
                                                }
                                            />
                                        </label>
                                        <label className="text-xs">
                                            Amount for size {index + 1}
                                            <input
                                                required
                                                type="number"
                                                min="0.001"
                                                step="0.001"
                                                max="999999999.999"
                                                value={choice.quantity}
                                                onChange={(event) =>
                                                    update(food.id, {
                                                        ...group,
                                                        choices:
                                                            group.choices.map(
                                                                (row, i) =>
                                                                    i === index
                                                                        ? {
                                                                              ...row,
                                                                              quantity:
                                                                                  event
                                                                                      .target
                                                                                      .value,
                                                                          }
                                                                        : row,
                                                            ),
                                                    })
                                                }
                                            />
                                        </label>
                                        <label className="text-xs">
                                            Extra price (PHP)
                                            <input
                                                required
                                                inputMode="decimal"
                                                readOnly={choice.is_default}
                                                value={choice.extra_price_pesos}
                                                onChange={(event) =>
                                                    update(food.id, {
                                                        ...group,
                                                        choices:
                                                            group.choices.map(
                                                                (row, i) =>
                                                                    i === index
                                                                        ? {
                                                                              ...row,
                                                                              extra_price_pesos:
                                                                                  event
                                                                                      .target
                                                                                      .value,
                                                                          }
                                                                        : row,
                                                            ),
                                                    })
                                                }
                                            />
                                        </label>
                                        <label className="flex items-center gap-2 text-xs">
                                            <input
                                                type="radio"
                                                name={`size-default-${food.id}`}
                                                checked={choice.is_default}
                                                onChange={() =>
                                                    update(food.id, {
                                                        ...group,
                                                        choices:
                                                            group.choices.map(
                                                                (row, i) => ({
                                                                    ...row,
                                                                    is_default:
                                                                        i ===
                                                                        index,
                                                                    extra_price_pesos:
                                                                        i ===
                                                                        index
                                                                            ? "0"
                                                                            : row.extra_price_pesos,
                                                                }),
                                                            ),
                                                    })
                                                }
                                            />
                                            Included default
                                        </label>
                                        <button
                                            type="button"
                                            className="text-xs text-rose-700 underline"
                                            onClick={() => {
                                                const remaining =
                                                    group.choices.filter(
                                                        (_, i) => i !== index,
                                                    );
                                                if (
                                                    choice.is_default &&
                                                    remaining.length
                                                )
                                                    remaining[0] = {
                                                        ...remaining[0],
                                                        is_default: true,
                                                        extra_price_pesos: "0",
                                                    };
                                                update(food.id, {
                                                    ...group,
                                                    choices: remaining,
                                                });
                                            }}
                                        >
                                            Remove size {index + 1}
                                        </button>
                                        <p className="text-xs text-slate-600 sm:col-span-2">
                                            {choice.quantity || "—"}{" "}
                                            {choice.size_label}{" "}
                                            {unit?.abbreviation ?? "units"} ·{" "}
                                            {choice.is_default
                                                ? "Included"
                                                : `+₱${choice.extra_price_pesos || "0"} per package`}
                                        </p>
                                    </div>
                                ))}
                                <button
                                    type="button"
                                    className="text-sm text-emerald-800 underline"
                                    onClick={() =>
                                        update(food.id, {
                                            ...group,
                                            choices: [
                                                ...group.choices,
                                                {
                                                    quantity: "",
                                                    extra_price_pesos: "0",
                                                    is_default:
                                                        group.choices.length ===
                                                        0,
                                                },
                                            ],
                                        })
                                    }
                                >
                                    Add size for {food.name}
                                </button>
                            </div>
                        )}
                    </fieldset>
                );
            })}
        </section>
    );
}
