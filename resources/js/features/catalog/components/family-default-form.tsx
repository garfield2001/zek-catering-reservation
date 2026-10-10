import { useForm } from "@inertiajs/react";
import { Button } from "../../../components/ui/button";
import { Field } from "../../../components/ui/field";
import type { Family } from "../types";
import { catalogPaths } from "../paths";
export function FamilyDefaultForm({ family }: { family: Family }) {
    const setting = family.order_default!;
    const unit =
        family.code === "main_catering"
            ? "pax"
            : family.code === "food_pack"
              ? "packs"
              : "sets";
    const form = useForm({
        minimum_quantity: setting.minimum_quantity,
        quantity_step: setting.quantity_step,
        maximum_quantity: setting.maximum_quantity,
        enforce_minimum_floor: setting.enforce_minimum_floor,
    });
    return (
        <form
            className="space-y-4 rounded-xl border border-stone-200 bg-white p-5"
            onSubmit={(event) => {
                event.preventDefault();
                form.put(catalogPaths.families + "/" + family.id + "/defaults");
            }}
        >
            <div>
                <h2 className="font-semibold">{family.name}</h2>
                <p className="text-xs text-stone-500">{family.code}</p>
            </div>
            <div className="grid gap-4 sm:grid-cols-3">
                <Field
                    id={"minimum-" + family.id}
                    label={`Minimum (${unit})`}
                    error={form.errors.minimum_quantity}
                >
                    <input
                        id={"minimum-" + family.id}
                        type="number"
                        min="1"
                        required
                        value={form.data.minimum_quantity}
                        onChange={(e) =>
                            form.setData(
                                "minimum_quantity",
                                Number(e.target.value),
                            )
                        }
                    />
                </Field>
                <Field
                    id={"step-" + family.id}
                    label={`Increment (${unit})`}
                    error={form.errors.quantity_step}
                >
                    <input
                        id={"step-" + family.id}
                        type="number"
                        min="1"
                        required
                        value={form.data.quantity_step}
                        onChange={(e) =>
                            form.setData(
                                "quantity_step",
                                Number(e.target.value),
                            )
                        }
                    />
                </Field>
                <Field
                    id={"maximum-" + family.id}
                    label={`Maximum (${unit}, optional)`}
                    error={form.errors.maximum_quantity}
                >
                    <input
                        id={"maximum-" + family.id}
                        type="number"
                        min="1"
                        value={form.data.maximum_quantity ?? ""}
                        onChange={(e) =>
                            form.setData(
                                "maximum_quantity",
                                e.target.value === ""
                                    ? null
                                    : Number(e.target.value),
                            )
                        }
                    />
                </Field>
            </div>
            <label className="flex items-center gap-2 text-sm">
                <input
                    type="checkbox"
                    checked={form.data.enforce_minimum_floor}
                    onChange={(e) =>
                        form.setData("enforce_minimum_floor", e.target.checked)
                    }
                />
                Prevent future variants from lowering this family minimum
            </label>
            {family.code === "main_catering" && (
                <p className="text-xs text-amber-800">
                    {family.name} currently has a minimum of{" "}
                    {setting.minimum_quantity} pax. Changing the floor is an
                    audited Admin action.
                </p>
            )}
            <Button disabled={form.processing}>Save defaults</Button>
        </form>
    );
}
