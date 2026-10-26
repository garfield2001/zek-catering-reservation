import { useState } from "react";
import { Check, LoaderCircle, Plus } from "lucide-react";
import { FoodImage } from "../../../components/ui/food-image";
import { csrfFetch } from "../../../lib/http";
import { validationErrors } from "../../../lib/validation-errors";
import { formatPesos } from "../../packages/money";
import type { PublicListing } from "../types";

export function ListingCard({
    listing,
    selectionUrl,
    onSelected,
}: {
    listing: PublicListing;
    selectionUrl: string;
    onSelected?: (count: number) => void;
}) {
    const [quantity, setQuantity] = useState(listing.minimum_quantity);
    const [optionId, setOptionId] = useState<number | null>(
        listing.selling_options.length === 1
            ? listing.selling_options[0].id
            : null,
    );
    const [total, setTotal] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [saving, setSaving] = useState(false);
    const displayPrice = optionId
        ? (listing.selling_options.find((option) => option.id === optionId)
              ?.price_minor ?? listing.price_minor)
        : listing.price_minor;
    async function add() {
        setSaving(true);
        setError(null);
        try {
            const response = await csrfFetch(selectionUrl, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ quantity, selling_option_id: optionId }),
            });
            const data: unknown = await response.json();
            if (!response.ok) {
                setError(
                    Object.values(validationErrors(data))[0] ??
                        "We could not add this item.",
                );
                return;
            }
            const result = data as {
                preview: { total_minor: string };
                selection_count: number;
            };
            setTotal(result.preview.total_minor);
            onSelected?.(result.selection_count);
        } catch {
            setError("We could not add this item. Please try again.");
        } finally {
            setSaving(false);
        }
    }
    return (
        <article className="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
            <div className="flex gap-4">
                <FoodImage
                    compact
                    name={listing.food.name}
                    src={listing.food.thumbnail_url}
                />
                <div className="min-w-0">
                    <p className="text-xs font-bold uppercase tracking-[0.14em] text-amber-700">
                        {listing.category.name}
                    </p>
                    <h3 className="mt-1 text-lg font-bold text-emerald-950">
                        {listing.name}
                    </h3>
                    {listing.description && (
                        <p className="mt-1 text-sm text-stone-600">
                            {listing.description}
                        </p>
                    )}
                </div>
            </div>
            <div className="mt-4 flex items-end justify-between border-t border-stone-100 pt-4">
                <span className="text-lg font-bold text-emerald-900">
                    {formatPesos(displayPrice)}
                </span>
                <span className="text-xs text-stone-500">
                    per {listing.selling_unit}
                </span>
            </div>
            {listing.selling_options.length > 0 && (
                <label className="mt-4 block text-sm font-semibold">
                    Option
                    <select
                        value={optionId ?? ""}
                        onChange={(event) =>
                            setOptionId(
                                event.target.value
                                    ? Number(event.target.value)
                                    : null,
                            )
                        }
                        className="mt-1"
                    >
                        <option value="">Choose an option</option>
                        {listing.selling_options.map((option) => (
                            <option key={option.id} value={option.id}>
                                {option.label} —{" "}
                                {formatPesos(option.price_minor)}
                            </option>
                        ))}
                    </select>
                </label>
            )}
            <label className="mt-4 block text-sm font-semibold">
                Quantity
                <input
                    type="number"
                    min={listing.minimum_quantity}
                    step={listing.quantity_step}
                    value={quantity}
                    onChange={(event) =>
                        setQuantity(Number(event.target.value))
                    }
                    className="mt-1"
                />
            </label>
            <p className="mt-1 text-xs text-stone-500">
                Minimum {listing.minimum_quantity}; increase by{" "}
                {listing.quantity_step}.
                {listing.maximum_quantity
                    ? ` Maximum ${listing.maximum_quantity}.`
                    : ""}
            </p>
            {error && (
                <p role="alert" className="mt-3 text-sm text-red-700">
                    {error}
                </p>
            )}
            {total && (
                <p className="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-emerald-800">
                    <Check size={16} /> Added: {formatPesos(total)}
                </p>
            )}
            <button
                type="button"
                disabled={saving}
                onClick={add}
                className="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-800 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-900"
            >
                {saving ? (
                    <LoaderCircle className="animate-spin" size={17} />
                ) : (
                    <Plus size={17} />
                )}{" "}
                Add to My Selection
            </button>
        </article>
    );
}
