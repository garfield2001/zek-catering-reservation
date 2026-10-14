import { useForm } from "@inertiajs/react";
import { UnsavedChanges } from "../../../components/ui/unsaved-changes";
import { Button } from "../../../components/ui/button";
import { Field } from "../../../components/ui/field";
import { FoodImage } from "../../../components/ui/food-image";
import { minorToPesoInput } from "../../packages/money";
import type { ListingEditorProps } from "../types";

export function ListingForm({
    family,
    listing,
    version,
    foods,
    canPrice,
}: ListingEditorProps) {
    const form = useForm({
        family_id: family.id,
        dish_id: listing?.dish_id ?? "",
        offering_option_id: listing?.offering_option_id?.toString() ?? "",
        creation_token: listing ? undefined : crypto.randomUUID(),
        version_id: version?.id,
        draft_revision: version?.draft_revision,
        name: version?.name ?? "",
        description: version?.description ?? "",
        selling_unit: version?.selling_unit ?? "item",
        price_pesos: version ? minorToPesoInput(version.price_minor) : "",
        minimum_quantity: String(version?.minimum_quantity ?? 1),
        quantity_step: String(version?.quantity_step ?? 1),
        maximum_quantity: version?.maximum_quantity?.toString() ?? "",
    });
    const food = foods.find((f) => f.id === Number(form.data.dish_id));
    const options = food?.offerings.flatMap((o) => o.options) ?? [];
    return (
        <form
            className="max-w-3xl space-y-5 rounded-2xl border bg-white p-6"
            onSubmit={(e) => {
                e.preventDefault();
                form.transform((data) => ({
                    ...data,
                    offering_option_id: data.offering_option_id || null,
                    maximum_quantity: data.maximum_quantity || null,
                }));
                if (listing) form.put(`/admin/items/${listing.id}`);
                else form.post("/admin/items");
            }}
        >
            <UnsavedChanges dirty={form.isDirty} />
            <p className="text-sm text-stone-500">
                Each food and size is an independently priced item. Saving
                creates or updates a Draft; publication is separate.
            </p>
            {listing?.current_version_id && (
                <p className="rounded bg-emerald-50 p-3 text-sm">
                    Your current publication remains unchanged until the new
                    Draft is published.
                </p>
            )}
            {Object.entries(form.errors).map(([key, message]) => (
                <p key={key} role="alert" className="text-sm text-red-700">
                    {message}
                </p>
            ))}
            <Field id="listing-food" label="Food Item">
                <select
                    id="listing-food"
                    required
                    disabled={!!listing}
                    value={form.data.dish_id}
                    onChange={(e) =>
                        form.setData((data) => ({
                            ...data,
                            dish_id: Number(e.target.value),
                            offering_option_id: "",
                        }))
                    }
                >
                    <option value="">Choose an eligible food</option>
                    {foods.map((f) => (
                        <option key={f.id} value={f.id}>
                            {f.name}
                        </option>
                    ))}
                </select>
            </Field>
            {food && (
                <FoodImage name={food.name} src={food.thumbnail_url} compact />
            )}
            {!foods.length && (
                <p className="rounded bg-amber-50 p-3 text-sm">
                    Prepare an active food eligible for {family.name} in Food
                    Catalog before creating an item.
                </p>
            )}
            <Field id="listing-size" label="Serving size (optional)">
                <select
                    id="listing-size"
                    disabled={!!listing}
                    value={form.data.offering_option_id}
                    onChange={(e) =>
                        form.setData("offering_option_id", e.target.value)
                    }
                >
                    <option value="">No separate size</option>
                    {options.map((o) => (
                        <option key={o.id} value={o.id}>
                            {o.label} · {o.quantity} {o.unit}
                        </option>
                    ))}
                </select>
                <p className="mt-1 text-xs text-stone-500">
                    Set up additional sizes in Food Catalog. The food and size
                    cannot change after creation; create another listing for a
                    different size.
                </p>
            </Field>
            <Field id="listing-name" label="Item Name">
                <input
                    id="listing-name"
                    required
                    maxLength={255}
                    value={form.data.name}
                    onChange={(e) => form.setData("name", e.target.value)}
                />
            </Field>
            <Field id="listing-description" label="Description (optional)">
                <textarea
                    id="listing-description"
                    maxLength={5000}
                    value={form.data.description}
                    onChange={(e) =>
                        form.setData("description", e.target.value)
                    }
                />
            </Field>
            {!canPrice && (
                <p className="text-sm text-amber-800">
                    Pricing and purchase quantities are read-only. Ask an
                    Administrator for listing pricing permission to change them.
                </p>
            )}
            <fieldset
                disabled={!canPrice}
                className="grid gap-4 sm:grid-cols-2"
            >
                <Field id="listing-unit" label="Sold per">
                    <select
                        id="listing-unit"
                        value={form.data.selling_unit}
                        onChange={(e) =>
                            form.setData("selling_unit", e.target.value)
                        }
                    >
                        {["item", "tray", "pack", "piece", "bottle", "kg"].map(
                            (unit) => (
                                <option key={unit}>{unit}</option>
                            ),
                        )}
                    </select>
                </Field>
                <Field
                    id="listing-price"
                    label={`Price per ${form.data.selling_unit} (PHP)`}
                >
                    <input
                        id="listing-price"
                        required
                        inputMode="decimal"
                        value={form.data.price_pesos}
                        onChange={(e) =>
                            form.setData("price_pesos", e.target.value)
                        }
                    />
                </Field>
                <Field id="listing-minimum" label="Minimum Purchase">
                    <input
                        id="listing-minimum"
                        type="number"
                        min="1"
                        required
                        value={form.data.minimum_quantity}
                        onChange={(e) =>
                            form.setData("minimum_quantity", e.target.value)
                        }
                    />
                </Field>
                <Field id="listing-step" label="Quantity increment">
                    <input
                        id="listing-step"
                        type="number"
                        min="1"
                        required
                        value={form.data.quantity_step}
                        onChange={(e) =>
                            form.setData("quantity_step", e.target.value)
                        }
                    />
                </Field>
                <Field id="listing-maximum" label="Maximum Purchase (optional)">
                    <input
                        id="listing-maximum"
                        type="number"
                        min={form.data.minimum_quantity}
                        value={form.data.maximum_quantity}
                        onChange={(e) =>
                            form.setData("maximum_quantity", e.target.value)
                        }
                    />
                </Field>
            </fieldset>
            <Button disabled={form.processing || (!listing && !foods.length)}>
                {form.processing ? "Saving…" : "Save Item Draft"}
            </Button>
        </form>
    );
}
