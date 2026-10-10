import type { AvailableOption, PackageGroup } from "../types";
import type { GroupOptionInput } from "./group-form-types";

export function GroupOptionPicker({
    available,
    selected,
    group,
    mode,
    familyCode,
    canPrice,
    error,
    onChange,
}: {
    available: AvailableOption[];
    selected: GroupOptionInput[];
    group: PackageGroup | null;
    mode: PackageGroup["mode"];
    familyCode: string;
    canPrice: boolean;
    error?: string;
    onChange: (options: GroupOptionInput[]) => void;
}) {
    const toggle = (id: number, checked: boolean) =>
        onChange(
            checked
                ? [
                      ...selected,
                      {
                          offering_option_id: id,
                          is_default: mode === "fixed",
                          price_adjustment_minor: "0",
                      },
                  ]
                : selected.filter((option) => option.offering_option_id !== id),
        );
    const change = (id: number, changes: Partial<GroupOptionInput>) =>
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
                Only active, family-eligible options appear. Premiums are
                centavos per pax, pack or set—not per kilogram or tray size.
            </p>
            {error && (
                <p role="alert" className="text-sm text-red-700">
                    {error}
                </p>
            )}
            <div className="mt-4 max-h-80 space-y-2 overflow-y-auto">
                {available.map((option) => {
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
                                    type="checkbox"
                                    checked={!!current}
                                    onChange={(event) =>
                                        toggle(option.id, event.target.checked)
                                    }
                                />
                                {option.dish_name} / {option.offering_name} /{" "}
                                {option.label} ({option.quantity} {option.unit})
                            </label>
                            {current && (
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={current.is_default}
                                        onChange={(event) =>
                                            change(option.id, {
                                                is_default:
                                                    event.target.checked,
                                            })
                                        }
                                    />
                                    Default
                                </label>
                            )}
                            {current && (
                                <label className="text-xs">
                                    Premium centavos
                                    <input
                                        className="mt-1 w-32"
                                        inputMode="numeric"
                                        disabled={
                                            !canPrice ||
                                            familyCode === "pica_pica"
                                        }
                                        value={current.price_adjustment_minor}
                                        onChange={(event) =>
                                            change(option.id, {
                                                price_adjustment_minor:
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
                    No active offering options exist for this family. Configure
                    the master catalog first.
                </p>
            )}
        </section>
    );
}
