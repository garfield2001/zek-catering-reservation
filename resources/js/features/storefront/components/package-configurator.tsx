import { useMemo, useState } from "react";
import { Check, LoaderCircle, Plus } from "lucide-react";
import { FoodImage } from "../../../components/ui/food-image";
import { csrfFetch } from "../../../lib/http";
import { validationErrors } from "../../../lib/validation-errors";
import { formatPesos } from "../../packages/money";
import type { PricePreview, PublicGroup, PublicPackage } from "../types";

type Props = {
    offering: PublicPackage;
    previewUrl: string;
    selectionUrl: string;
    onSelected?: (count: number) => void;
};

function defaultChoices(groups: PublicGroup[]): Record<string, number[]> {
    return Object.fromEntries(
        groups.map((group) => [
            group.group_code,
            group.mode === "fixed"
                ? []
                : group.options
                      .filter((option) => option.is_default)
                      .map((option) => option.offering_option_id),
        ]),
    );
}

export function PackageConfigurator({
    offering,
    previewUrl,
    selectionUrl,
    onSelected,
}: Props) {
    const [quantity, setQuantity] = useState(offering.terms.minimum_quantity);
    const [foodIds, setFoodIds] = useState<number[]>(
        () =>
            offering.menu?.food_items
                .filter((item) => item.is_fixed)
                .map((item) => item.id) ?? [],
    );
    const [choices, setChoices] = useState(() =>
        defaultChoices(offering.groups),
    );
    const [preview, setPreview] = useState<PricePreview | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState<
        "preview" | "selection" | null
    >(null);
    const payload = useMemo(
        () => ({
            quantity,
            groups: [
                {
                    quantity,
                    selections: offering.menu
                        ? { food_items: foodIds, food_sizes: {} }
                        : choices,
                },
            ],
        }),
        [choices, foodIds, offering.menu, quantity],
    );
    const foodLimit = offering.menu?.total_selection_count ?? 0;
    const categoryRules =
        offering.menu?.category_choice_rules ??
        offering.menu?.required_categories.map((category) => ({
            ...category,
            minimum_count: 1,
            maximum_count: 1,
        })) ??
        [];

    async function send(url: string, mode: "preview" | "selection") {
        setSubmitting(mode);
        setErrors({});
        try {
            const response = await csrfFetch(url, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(payload),
            });
            const data: unknown = await response.json();
            if (!response.ok) {
                setErrors(validationErrors(data));
                return;
            }
            const result = data as PricePreview & {
                preview?: PricePreview;
                selection_count?: number;
            };
            setPreview(result.preview ?? result);
            if (mode === "selection" && result.selection_count !== undefined)
                onSelected?.(result.selection_count);
        } catch {
            setErrors({
                package:
                    "We could not update this selection. Please try again.",
            });
        } finally {
            setSubmitting(null);
        }
    }

    function toggleFood(id: number, fixed?: boolean) {
        if (fixed) return;
        const food = offering.menu?.food_items.find((item) => item.id === id);
        const rule = categoryRules.find(
            (item) => item.id === food?.category_id,
        );
        setFoodIds((current) =>
            current.includes(id)
                ? current.filter((value) => value !== id)
                : current.length >= foodLimit ||
                    (rule &&
                        current.filter((value) =>
                            offering.menu?.food_items.some(
                                (item) =>
                                    item.id === value &&
                                    item.category_id === rule.id &&
                                    !item.is_fixed,
                            ),
                        ).length >= rule.maximum_count)
                  ? current
                  : [...current, id],
        );
    }
    function toggleOption(group: PublicGroup, id: number) {
        if (group.mode === "fixed") return;
        setChoices((current) => {
            const selected = current[group.group_code] ?? [];
            const next =
                group.mode === "select_one"
                    ? [id]
                    : selected.includes(id)
                      ? selected.filter((value) => value !== id)
                      : selected.length >= group.maximum_count
                        ? selected
                        : [...selected, id];
            return { ...current, [group.group_code]: next };
        });
    }

    return (
        <section
            className="rounded-2xl border border-emerald-100 bg-white p-5 shadow-sm sm:p-7"
            aria-labelledby="build-selection"
        >
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <div>
                    <p className="text-xs font-bold uppercase tracking-[0.16em] text-amber-700">
                        Planning estimate
                    </p>
                    <h2
                        id="build-selection"
                        className="mt-1 text-2xl font-bold text-emerald-950"
                    >
                        Build your selection
                    </h2>
                </div>
                <p className="text-sm text-stone-600">
                    {formatPesos(offering.terms.base_price_minor)}{" "}
                    {offering.terms.price_basis === "per_pax"
                        ? "per guest"
                        : offering.terms.price_basis === "per_pack"
                          ? "per pack"
                          : "per set"}
                </p>
            </div>
            <p className="mt-3 text-sm leading-6 text-stone-600">
                Choose from the currently available options. This is a planning
                selection, not a reservation or confirmed booking.
            </p>
            <label className="mt-6 block text-sm font-semibold text-stone-800">
                {offering.terms.price_basis === "per_pax"
                    ? "Number of guests"
                    : "Quantity"}
                <input
                    type="number"
                    min={offering.terms.minimum_quantity}
                    step={offering.terms.quantity_step}
                    value={quantity}
                    onChange={(event) =>
                        setQuantity(Number(event.target.value))
                    }
                    className="mt-2 max-w-xs"
                />
            </label>
            <p className="mt-1 text-xs text-stone-500">
                Minimum {offering.terms.minimum_quantity}; increase by{" "}
                {offering.terms.quantity_step}.
                {offering.terms.maximum_quantity
                    ? ` Maximum ${offering.terms.maximum_quantity}.`
                    : ""}
            </p>
            {offering.menu && (
                <fieldset className="mt-7">
                    <legend className="text-lg font-bold text-emerald-950">
                        Choose {foodLimit} Food Items{" "}
                        <span className="text-sm font-normal text-stone-500">
                            ({foodIds.length} selected)
                        </span>
                    </legend>
                    <p className="mt-1 text-sm text-stone-600">
                        Included Items are already part of your menu. Choose the
                        remaining Customer Choices.
                    </p>
                    {categoryRules.length > 0 && (
                        <ul className="mt-3 space-y-1 text-sm text-stone-600">
                            {categoryRules.map((rule) => (
                                <li key={rule.id}>
                                    {rule.name}: choose{" "}
                                    {rule.minimum_count === rule.maximum_count
                                        ? `exactly ${rule.minimum_count}`
                                        : `${rule.minimum_count} to ${rule.maximum_count}`}
                                    .
                                </li>
                            ))}
                        </ul>
                    )}
                    <div className="mt-4 grid gap-3 sm:grid-cols-2">
                        {offering.menu.food_items.map((food) => {
                            const selected = foodIds.includes(food.id);
                            return (
                                <button
                                    key={food.id}
                                    type="button"
                                    disabled={food.is_fixed}
                                    onClick={() =>
                                        toggleFood(food.id, food.is_fixed)
                                    }
                                    className={`flex items-center gap-3 rounded-xl border p-3 text-left ${selected ? "border-emerald-700 bg-emerald-50" : "border-stone-200 hover:border-emerald-300"} ${food.is_fixed ? "cursor-default" : ""}`}
                                >
                                    <FoodImage
                                        compact
                                        name={food.name}
                                        src={food.thumbnail_url}
                                    />
                                    <span className="min-w-0 flex-1">
                                        <span className="block font-semibold text-stone-900">
                                            {food.name}
                                        </span>
                                        <span className="text-xs text-stone-500">
                                            {food.is_fixed
                                                ? "Included Item"
                                                : food.category_name}
                                        </span>
                                    </span>
                                    {selected && (
                                        <Check
                                            className="text-emerald-700"
                                            aria-label="Selected"
                                        />
                                    )}
                                </button>
                            );
                        })}
                    </div>
                </fieldset>
            )}
            {!offering.menu &&
                offering.groups.map((group) => (
                    <fieldset className="mt-7" key={group.group_code}>
                        <legend className="text-lg font-bold text-emerald-950">
                            {group.name}
                        </legend>
                        <p className="mt-1 text-sm text-stone-600">
                            {group.mode === "fixed"
                                ? "Included Item"
                                : `Choose ${group.minimum_count === group.maximum_count ? group.minimum_count : `${group.minimum_count} to ${group.maximum_count}`} option${group.maximum_count === 1 ? "" : "s"}.`}
                        </p>
                        <div className="mt-3 grid gap-3 sm:grid-cols-2">
                            {group.options.map((option) => {
                                const selected =
                                    group.mode === "fixed" ||
                                    (choices[group.group_code] ?? []).includes(
                                        option.offering_option_id,
                                    );
                                return (
                                    <button
                                        type="button"
                                        key={option.offering_option_id}
                                        disabled={group.mode === "fixed"}
                                        onClick={() =>
                                            toggleOption(
                                                group,
                                                option.offering_option_id,
                                            )
                                        }
                                        className={`rounded-xl border p-3 text-left ${selected ? "border-emerald-700 bg-emerald-50" : "border-stone-200 hover:border-emerald-300"} ${group.mode === "fixed" ? "cursor-default" : ""}`}
                                    >
                                        <span className="flex items-center justify-between gap-3">
                                            <span className="font-semibold text-stone-900">
                                                {option.food_item.name}
                                            </span>
                                            {selected && (
                                                <Check
                                                    className="text-emerald-700"
                                                    aria-label="Selected"
                                                />
                                            )}
                                        </span>
                                        <span className="mt-1 block text-xs text-stone-500">
                                            {option.serving_option.label}
                                            {option.price_adjustment_minor !==
                                            "0"
                                                ? ` · +${formatPesos(option.price_adjustment_minor)}`
                                                : ""}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                    </fieldset>
                ))}
            {Object.values(errors).length > 0 && (
                <div
                    role="alert"
                    className="mt-6 rounded-xl bg-red-50 p-4 text-sm text-red-800"
                >
                    {Object.values(errors)[0]}
                </div>
            )}
            {preview && (
                <div
                    className="mt-6 rounded-xl bg-emerald-950 p-5 text-white"
                    aria-live="polite"
                >
                    <p className="text-sm text-emerald-100">
                        Current planning estimate
                    </p>
                    <strong className="mt-1 block text-3xl">
                        {formatPesos(preview.total_minor)}
                    </strong>
                    <p className="mt-1 text-sm text-emerald-100">
                        Calculated from current published pricing. Final
                        arrangements are confirmed with our team.
                    </p>
                </div>
            )}
            <div className="mt-6 flex flex-col gap-3 sm:flex-row">
                <button
                    type="button"
                    onClick={() => send(previewUrl, "preview")}
                    disabled={submitting !== null}
                    className="inline-flex items-center justify-center gap-2 rounded-xl border border-emerald-800 px-5 py-3 font-semibold text-emerald-900 hover:bg-emerald-50"
                >
                    {submitting === "preview" && (
                        <LoaderCircle className="animate-spin" size={17} />
                    )}{" "}
                    Update estimate
                </button>
                <button
                    type="button"
                    onClick={() => send(selectionUrl, "selection")}
                    disabled={submitting !== null}
                    className="inline-flex items-center justify-center gap-2 rounded-xl bg-amber-600 px-5 py-3 font-semibold text-white hover:bg-amber-700"
                >
                    {submitting === "selection" ? (
                        <LoaderCircle className="animate-spin" size={17} />
                    ) : (
                        <Plus size={17} />
                    )}{" "}
                    Add to My Selection
                </button>
            </div>
        </section>
    );
}
