import { useState } from "react";
import type { AvailableServingOption, SelectionGroup } from "../types";
import type { GroupAllowedOptionInput } from "./selection-group-form-types";

export function GroupAllowedOptionPicker({
    available,
    selected,
    group,
    mode,
    behaviorType,
    canPrice,
    error,
    onChange,
}: {
    available: AvailableServingOption[];
    selected: GroupAllowedOptionInput[];
    group: SelectionGroup | null;
    mode: SelectionGroup["mode"];
    behaviorType: string;
    canPrice: boolean;
    error?: string;
    onChange: (options: GroupAllowedOptionInput[]) => void;
}) {
    const [search, setSearch] = useState("");
    const toggle = (id: number, checked: boolean) =>
        onChange(
            checked
                ? [
                      ...(mode === "fixed" ? [] : selected),
                      {
                          offering_option_id: id,
                          is_default: mode === "fixed",
                          price_adjustment_pesos: "0.00",
                      },
                  ]
                : selected.filter((option) => option.offering_option_id !== id),
        );
    const change = (id: number, changes: Partial<GroupAllowedOptionInput>) =>
        onChange(
            selected.map((option) =>
                option.offering_option_id === id
                    ? { ...option, ...changes }
                    : option,
            ),
        );
    const unavailable = selected.filter(
        (item) =>
            !available.some((option) => option.id === item.offering_option_id),
    );
    return (
        <section className="rounded-xl border border-stone-200 bg-white p-5">
            <h2 className="font-semibold">Permitted serving options</h2>
            <p className="mt-1 text-sm text-stone-600">
                Only active, collection-eligible options appear. Price
                Adjustments are in Philippine pesos per pax, pack or set.
            </p>
            {error && (
                <p role="alert" className="text-sm text-red-700">
                    {error}
                </p>
            )}
            {available.length > 8 && (
                <input
                    type="search"
                    aria-label="Find serving option"
                    placeholder="Find food item or serving option"
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    className="mt-4 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm"
                />
            )}
            <div className="mt-4 max-h-80 space-y-2 overflow-y-auto">
                {available
                    .filter((option) =>
                        `${option.dish_name} ${option.offering_name} ${option.label}`
                            .toLocaleLowerCase()
                            .includes(search.toLocaleLowerCase()),
                    )
                    .map((option) => {
                        const current = selected.find(
                            (item) => item.offering_option_id === option.id,
                        );
                        return (
                            <div
                                key={option.id}
                                className="grid gap-2 rounded-lg border border-stone-200 p-3 sm:grid-cols-[1fr_auto_auto]"
                            >
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type={
                                            mode === "fixed"
                                                ? "radio"
                                                : "checkbox"
                                        }
                                        name={
                                            mode === "fixed"
                                                ? "fixed-option"
                                                : undefined
                                        }
                                        checked={!!current}
                                        onChange={(event) =>
                                            toggle(
                                                option.id,
                                                event.target.checked,
                                            )
                                        }
                                    />
                                    {option.dish_name} / {option.offering_name}{" "}
                                    / {option.label} ({option.quantity}{" "}
                                    {option.unit})
                                </label>
                                {current && (
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type={
                                                mode === "select_one"
                                                    ? "radio"
                                                    : "checkbox"
                                            }
                                            name={
                                                mode === "select_one"
                                                    ? "default-option"
                                                    : undefined
                                            }
                                            checked={current.is_default}
                                            onChange={(event) =>
                                                onChange(
                                                    selected.map((row) => ({
                                                        ...row,
                                                        is_default:
                                                            row.offering_option_id ===
                                                                option.id &&
                                                            event.target
                                                                .checked,
                                                    })),
                                                )
                                            }
                                        />
                                        Default
                                    </label>
                                )}
                                {current && (
                                    <label className="text-xs">
                                        Price Adjustment (PHP)
                                        <input
                                            className="mt-1 w-32"
                                            inputMode="numeric"
                                            disabled={
                                                !canPrice ||
                                                behaviorType === "fixed_bundle"
                                            }
                                            value={
                                                current.price_adjustment_pesos
                                            }
                                            onChange={(event) =>
                                                change(option.id, {
                                                    price_adjustment_pesos:
                                                        event.target.value,
                                                })
                                            }
                                        />
                                    </label>
                                )}
                            </div>
                        );
                    })}
            </div>
            {unavailable.map((item) => {
                const former = group?.options?.find(
                    (option) =>
                        option.offering_option_id === item.offering_option_id,
                );
                return (
                    <div
                        key={item.offering_option_id}
                        className="mt-2 flex items-center justify-between rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm"
                    >
                        <span>
                            {former?.offering_option?.offering?.dish?.name ??
                                `Option ${item.offering_option_id}`}{" "}
                            is no longer available and must be removed before
                            saving.
                        </span>
                        <button
                            type="button"
                            className="text-red-700 underline"
                            onClick={() =>
                                toggle(item.offering_option_id, false)
                            }
                        >
                            Remove
                        </button>
                    </div>
                );
            })}
            {available.length === 0 && (
                <p className="mt-4 text-sm text-amber-800">
                    No active serving options exist for this collection.
                    Configure the master catalog first.
                </p>
            )}
        </section>
    );
}
