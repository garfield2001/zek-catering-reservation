import { useDialogForm } from "../../../components/ui/dialog-form-guard";
import { useForm } from "@inertiajs/react";
import { UnsavedChanges } from "../../../components/ui/unsaved-changes";
import { Button } from "../../../components/ui/button";
import { Field } from "../../../components/ui/field";
import { FoodImage } from "../../../components/ui/food-image";
import { minorToPesoInput } from "../../packages/money";
import type { ListingEditorProps } from "../types";
import { SellingOptionFields } from "./selling-option-fields";
import { useJsonSave } from "../../catalog/use-json-save";

export function ListingForm({
    family,
    listing,
    version,
    foods,
    canPrice,
    onSaved,
}: ListingEditorProps & { onSaved?: (id: number) => void }) {
    const request = useJsonSave<{ id: number }>();
    const form = useForm({
        family_id: family.id,
        dish_id: listing?.dish_id ?? "",
        offering_option_id: listing?.offering_option_id?.toString() ?? "",
        creation_token: listing ? undefined : crypto.randomUUID(),
        version_id: version?.id,
        draft_revision: version?.draft_revision,
        name: version?.uses_food_name ? "" : (version?.name ?? ""),
        description: version?.description ?? "",
        selling_unit: version?.selling_unit ?? "item",
        selling_options: version?.selling_options?.length
            ? version.selling_options.map((option) => ({
                  label: option.label,
                  price_pesos: minorToPesoInput(option.price_minor),
              }))
            : [
                  {
                      label: "Standard",
                      price_pesos: version
                          ? minorToPesoInput(version.price_minor)
                          : "",
                  },
              ],
        minimum_quantity: String(version?.minimum_quantity ?? 1),
        quantity_step: String(version?.quantity_step ?? 1),
        maximum_quantity: version?.maximum_quantity?.toString() ?? "",
    });
    const food = foods.find((f) => f.id === Number(form.data.dish_id));
    useDialogForm(form.isDirty, form.processing || request.saving);
    return (
        <form
            className="max-w-3xl space-y-5 rounded-2xl border bg-white p-6"
            onSubmit={async (e) => {
                e.preventDefault();
                if (request.saving) return;
                if (onSaved) {
                    form.clearErrors();
                    const result = await request.save(
                        listing ? `/admin/items/${listing.id}` : "/admin/items",
                        listing ? "PUT" : "POST",
                        {
                            ...form.data,
                            offering_option_id:
                                form.data.offering_option_id || null,
                            maximum_quantity:
                                form.data.maximum_quantity || null,
                        },
                        form.setError,
                    );
                    if (result) {
                        form.setDefaults(form.data);
                        onSaved(result.id);
                    }
                    return;
                }
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
            {request.error && <p role="alert">{request.error}</p>}
            <p className="text-sm text-stone-500">
                One Food Item can have multiple selling sizes and prices. Saving
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
                    <option value="">Choose an available food</option>
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
                    Prepare an active food for {family.name} in Food Catalog
                    before creating an item.
                </p>
            )}
            {listing?.offering_option_id && (
                <p className="text-sm text-slate-600">
                    This existing listing retains its original catalog Serving
                    Option reference. Its historical source is unchanged.
                </p>
            )}
            <Field id="listing-name" label="Item Name (optional override)">
                <input
                    id="listing-name"
                    maxLength={255}
                    placeholder={food?.name ?? "Uses the Food Item name"}
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
                <SellingOptionFields
                    value={form.data.selling_options}
                    unit={form.data.selling_unit}
                    onChange={(value) => form.setData("selling_options", value)}
                />
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
            <Button
                disabled={
                    form.processing ||
                    request.saving ||
                    (!listing && !foods.length)
                }
            >
                {form.processing ? "Saving…" : "Save Item Draft"}
            </Button>
        </form>
    );
}
