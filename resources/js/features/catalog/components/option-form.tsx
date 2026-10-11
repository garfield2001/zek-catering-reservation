import { useForm } from "@inertiajs/react";
import { Button } from "../../../components/ui/button";
import { Field } from "../../../components/ui/field";
import type { FoodItem, Offering, ServingOption } from "../types";
import { catalogPaths } from "../paths";
const units = [
    { value: "kg", label: "Kilograms" },
    { value: "tray", label: "Trays" },
    { value: "portion", label: "Portions" },
    { value: "piece", label: "Pieces" },
    { value: "bottle", label: "Bottles" },
    { value: "pack", label: "Packs" },
];
export function OptionForm({
    dish,
    offering,
    option,
}: {
    dish: FoodItem;
    offering: Offering;
    option: ServingOption | null;
}) {
    const form = useForm({
        label: option?.label ?? "",
        unit: option?.unit ?? "tray",
        quantity: option?.quantity ?? "1",
        serving_pax: option?.serving_pax ?? (null as number | null),
        serving_claim: option?.serving_claim ?? "",
        active: option?.active ?? true,
    });
    return (
        <form
            className="max-w-xl space-y-5 rounded-xl border border-stone-200 bg-white p-6"
            onSubmit={(event) => {
                event.preventDefault();
                if (option)
                    form.put(
                        catalogPaths.option(dish.id, offering.id, option.id),
                    );
                else
                    form.post(
                        catalogPaths.offering(dish.id, offering.id) +
                            "/options",
                    );
            }}
        >
            <Field id="label" label="Serving label" error={form.errors.label}>
                <input
                    id="label"
                    value={form.data.label}
                    required
                    maxLength={255}
                    placeholder="Regular tray"
                    onChange={(e) => form.setData("label", e.target.value)}
                />
            </Field>
            <div className="grid gap-4 sm:grid-cols-2">
                <Field id="unit" label="Unit" error={form.errors.unit}>
                    <select
                        id="unit"
                        value={form.data.unit}
                        onChange={(e) => form.setData("unit", e.target.value)}
                    >
                        {units.map((unit) => (
                            <option key={unit.value} value={unit.value}>
                                {unit.label}
                            </option>
                        ))}
                    </select>
                </Field>
                <Field
                    id="quantity"
                    label="Size per option"
                    error={form.errors.quantity}
                >
                    <input
                        id="quantity"
                        type="number"
                        min="0.001"
                        max="999999999.999"
                        step="0.001"
                        required
                        value={form.data.quantity}
                        onChange={(e) =>
                            form.setData("quantity", e.target.value)
                        }
                    />
                </Field>
            </div>
            <Field
                id="serving_pax"
                label="Approximate guest capacity (optional)"
                error={form.errors.serving_pax}
            >
                <input
                    id="serving_pax"
                    type="number"
                    min="1"
                    value={form.data.serving_pax ?? ""}
                    onChange={(e) =>
                        form.setData(
                            "serving_pax",
                            e.target.value === ""
                                ? null
                                : Number(e.target.value),
                        )
                    }
                />
            </Field>
            <Field
                id="serving_claim"
                label="Serving description (optional)"
                error={form.errors.serving_claim}
            >
                <input
                    id="serving_claim"
                    value={form.data.serving_claim}
                    maxLength={255}
                    placeholder="Approx. 15 guests"
                    onChange={(e) =>
                        form.setData("serving_claim", e.target.value)
                    }
                />
            </Field>
            {form.data.unit === "kg" && (
                <p className="text-sm text-stone-600">
                    Kilograms do not imply a guest capacity. Enter one only if
                    separately verified.
                </p>
            )}
            <label className="flex items-center gap-2 text-sm">
                <input
                    type="checkbox"
                    checked={form.data.active}
                    onChange={(e) => form.setData("active", e.target.checked)}
                />
                Active option
            </label>
            <Button disabled={form.processing}>
                {option ? "Save serving option" : "Create serving option"}
            </Button>
        </form>
    );
}
