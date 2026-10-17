import { Field } from "../../../components/ui/field";

export type SellingOptionInput = { label: string; price_pesos: string };

export function SellingOptionFields({
    value,
    unit,
    onChange,
}: {
    value: SellingOptionInput[];
    unit: string;
    onChange: (options: SellingOptionInput[]) => void;
}) {
    return (
        <section
            aria-label="Selling options"
            className="space-y-3 sm:col-span-2"
        >
            <h3 className="font-semibold">Selling options</h3>
            <p className="text-sm text-slate-600">
                Keep sizes together under this Food Item. Each price is the full
                selling price, not an extra charge. Purchase limits below apply
                to each order line.
            </p>
            {value.map((option, index) => (
                <div
                    key={index}
                    className="grid gap-3 rounded-xl border p-3 sm:grid-cols-2"
                >
                    <Field
                        id={`selling-option-label-${index}`}
                        label={`Option ${index + 1} name`}
                    >
                        <input
                            id={`selling-option-label-${index}`}
                            required
                            maxLength={100}
                            value={option.label}
                            onChange={(event) =>
                                onChange(
                                    value.map((row, i) =>
                                        i === index
                                            ? {
                                                  ...row,
                                                  label: event.target.value,
                                              }
                                            : row,
                                    ),
                                )
                            }
                        />
                    </Field>
                    <Field
                        id={`selling-option-price-${index}`}
                        label={
                            index === 0
                                ? `Price per ${unit} (PHP)`
                                : `Price for option ${index + 1} (PHP)`
                        }
                    >
                        <input
                            id={`selling-option-price-${index}`}
                            required
                            inputMode="decimal"
                            value={option.price_pesos}
                            onChange={(event) =>
                                onChange(
                                    value.map((row, i) =>
                                        i === index
                                            ? {
                                                  ...row,
                                                  price_pesos:
                                                      event.target.value,
                                              }
                                            : row,
                                    ),
                                )
                            }
                        />
                    </Field>
                    {value.length > 1 && (
                        <button
                            type="button"
                            className="text-left text-sm text-rose-700 underline"
                            onClick={() =>
                                onChange(value.filter((_, i) => i !== index))
                            }
                        >
                            Remove option {index + 1}
                        </button>
                    )}
                </div>
            ))}
            <button
                type="button"
                className="text-sm text-emerald-800 underline"
                onClick={() =>
                    onChange([...value, { label: "", price_pesos: "" }])
                }
            >
                Add Selling Option
            </button>
        </section>
    );
}
