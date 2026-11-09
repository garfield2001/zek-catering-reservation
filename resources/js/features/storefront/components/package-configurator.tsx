import { Link } from "@inertiajs/react";
import { useMemo, useState } from "react";
import { Check, LoaderCircle, Plus } from "lucide-react";
import { ConfirmDialog } from "../../../components/ui/confirm-dialog";
import { FoodImage } from "../../../components/ui/food-image";
import { csrfFetch } from "../../../lib/http";
import { validationErrors } from "../../../lib/validation-errors";
import { formatPesos } from "../../packages/money";
import type {
    PricePreview,
    PublicFood,
    PublicGroup,
    PublicPackage,
} from "../types";
import { FoodImagePreview } from "./food-image-preview";

type Props = {
    offering: PublicPackage;
    previewUrl: string;
    selectionUrl?: string;
    activeMainProductId?: number | null;
    onSelected?: (count: number) => void;
    onConfigurationSaved?: (
        configuration: {
            quantity: number;
            groups: Array<{
                quantity: number;
                selections: Record<string, number[] | Record<string, number>>;
            }>;
        },
        preview: PricePreview,
    ) => void;
    selectionLabel?: string;
    initialQuantity?: number;
    initialGroups?: Array<{
        quantity: number;
        selections: Record<string, unknown>;
    }>;
};

type CategoryRule = {
    id: number;
    name: string;
    minimum_count: number;
    maximum_count: number;
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
    activeMainProductId = null,
    onSelected,
    onConfigurationSaved,
    selectionLabel,
    initialQuantity,
    initialGroups,
}: Props) {
    const [quantity, setQuantity] = useState(
        initialQuantity ?? offering.terms.minimum_quantity,
    );
    const [foodIds, setFoodIds] = useState<number[]>(() => {
        const selected = initialGroups?.[0]?.selections.food_items;
        if (Array.isArray(selected) && selected.every(Number.isInteger)) {
            return selected as number[];
        }

        return (
            offering.menu?.food_items
                .filter((item) => item.is_fixed)
                .map((item) => item.id) ?? []
        );
    });
    const [choices, setChoices] = useState(() =>
        Object.fromEntries(
            offering.groups.map((group) => {
                const selected =
                    initialGroups?.[0]?.selections[group.group_code];
                return [
                    group.group_code,
                    Array.isArray(selected) && selected.every(Number.isInteger)
                        ? (selected as number[])
                        : defaultChoices([group])[group.group_code],
                ];
            }),
        ),
    );
    const [preview, setPreview] = useState<PricePreview | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState<
        "preview" | "selection" | null
    >(null);
    const [confirmReplacement, setConfirmReplacement] = useState(false);
    const [saved, setSaved] = useState(false);
    const [previewFood, setPreviewFood] = useState<PublicFood | null>(null);

    const foodLimit = offering.menu?.total_selection_count ?? 0;
    const categoryRules: CategoryRule[] =
        offering.menu?.category_choice_rules ??
        offering.menu?.required_categories.map((category) => ({
            ...category,
            minimum_count: 1,
            maximum_count: 1,
        })) ??
        [];
    const foodItems = offering.menu?.food_items ?? [];
    const fixedFoods = foodItems.filter((food) => food.is_fixed);
    const selectableFoods = foodItems.filter((food) => !food.is_fixed);
    const categories = Array.from(
        new Map(
            selectableFoods.map((food) => [
                food.category_id ?? -1,
                {
                    id: food.category_id ?? -1,
                    name: food.category_name ?? "Other Foods",
                },
            ]),
        ).values(),
    );
    const [activeCategoryId, setActiveCategoryId] = useState<number | null>(
        () => categories[0]?.id ?? null,
    );
    const activeFoods = selectableFoods.filter(
        (food) => (food.category_id ?? -1) === activeCategoryId,
    );
    const isMain = offering.family.behavior_type === "main_package";
    const hasOtherMain =
        isMain &&
        activeMainProductId !== null &&
        activeMainProductId !== offering.id;
    const payload = useMemo<{
        quantity: number;
        groups: Array<{
            quantity: number;
            selections: Record<string, number[] | Record<string, number>>;
        }>;
    }>(
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

    function ruleFor(food: PublicFood) {
        return categoryRules.find((rule) => rule.id === food.category_id);
    }

    function selectedInCategory(categoryId: number) {
        return foodIds.filter((id) => {
            const food = offering.menu?.food_items.find(
                (item) => item.id === id,
            );
            return food?.category_id === categoryId && !food.is_fixed;
        });
    }

    async function send(
        url: string,
        mode: "preview" | "selection",
        replaceMain = false,
    ) {
        if (mode === "selection" && onConfigurationSaved) {
            setSubmitting(mode);
            setErrors({});
            try {
                const response = await csrfFetch(previewUrl, {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify(payload),
                });
                const data: unknown = await response.json();
                if (!response.ok) {
                    setErrors(validationErrors(data));
                    return;
                }
                setPreview(data as PricePreview);
                onConfigurationSaved(payload, data as PricePreview);
                setSaved(true);
            } catch {
                setErrors({
                    package:
                        "We could not validate this menu. Please try again.",
                });
            } finally {
                setSubmitting(null);
            }
            return;
        }
        if (!selectionUrl && mode === "selection") return;
        setSubmitting(mode);
        setErrors({});
        try {
            const response = await csrfFetch(url, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    ...payload,
                    ...(replaceMain ? { replace_main: true } : {}),
                }),
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
            if (mode === "selection" && result.selection_count !== undefined) {
                setSaved(true);
                onSelected?.(result.selection_count);
            }
        } catch {
            setErrors({
                package: "We could not update this inquiry. Please try again.",
            });
        } finally {
            setSubmitting(null);
        }
    }

    function toggleFood(id: number) {
        const food = offering.menu?.food_items.find((item) => item.id === id);
        if (!food || food.is_fixed) return;
        const rule = ruleFor(food);
        const inCategory = selectedInCategory(food.category_id ?? -1);

        setFoodIds((current) => {
            if (current.includes(id))
                return current.filter((value) => value !== id);
            if (rule?.maximum_count === 1 && inCategory.length > 0) {
                return [
                    ...current.filter((value) => !inCategory.includes(value)),
                    id,
                ];
            }
            if (
                current.length >= foodLimit ||
                (rule && inCategory.length >= rule.maximum_count)
            ) {
                return current;
            }

            return [...current, id];
        });
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
                Choose from currently available options. This is a planning
                inquiry, not a reservation or confirmed booking.
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
                        remaining Customer Choices by Food Category.
                    </p>
                    {fixedFoods.length > 0 && (
                        <section
                            className="mt-4 rounded-xl bg-emerald-50 p-4"
                            aria-label="Included Food Items"
                        >
                            <h3 className="font-semibold text-emerald-950">
                                Included Food Items
                            </h3>
                            <p className="mt-1 text-sm text-emerald-900">
                                These are fixed in every menu and do not use a
                                customer choice.
                            </p>
                            <ul className="mt-3 flex flex-wrap gap-2">
                                {fixedFoods.map((food) => (
                                    <li
                                        key={food.id}
                                        className="rounded-full bg-white px-3 py-1 text-sm text-emerald-950"
                                    >
                                        {food.name}
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}
                    {categories.length > 0 && (
                        <>
                            <div
                                role="tablist"
                                aria-label="Food Categories"
                                className="mt-5 flex gap-2 overflow-x-auto pb-2"
                            >
                                {categories.map((category) => {
                                    const rule = categoryRules.find(
                                        (item) => item.id === category.id,
                                    );
                                    const selected = selectedInCategory(
                                        category.id,
                                    ).length;
                                    const exact =
                                        rule &&
                                        rule.minimum_count ===
                                            rule.maximum_count;
                                    const complete =
                                        rule &&
                                        selected >= rule.minimum_count &&
                                        selected <= rule.maximum_count;
                                    const progress = rule
                                        ? `${category.name}: ${selected}/${rule.maximum_count}${complete ? " ✓" : ""}`
                                        : `${category.name}: ${selected} selected`;
                                    return (
                                        <button
                                            key={category.id}
                                            type="button"
                                            role="tab"
                                            aria-selected={
                                                activeCategoryId === category.id
                                            }
                                            aria-controls={`food-category-${category.id}`}
                                            onClick={() =>
                                                setActiveCategoryId(category.id)
                                            }
                                            className={`shrink-0 rounded-xl border px-3 py-2 text-left text-sm ${activeCategoryId === category.id ? "border-emerald-800 bg-emerald-800 text-white" : "border-stone-300 bg-white text-stone-800"}`}
                                        >
                                            <span className="block font-semibold">
                                                {progress}
                                            </span>
                                            <span
                                                className={`block text-xs ${activeCategoryId === category.id ? "text-emerald-100" : "text-stone-500"}`}
                                            >
                                                {rule?.minimum_count
                                                    ? exact
                                                        ? "Required: choose exactly"
                                                        : "Required category"
                                                    : "Optional category"}
                                            </span>
                                        </button>
                                    );
                                })}
                            </div>
                            <div
                                id={`food-category-${activeCategoryId}`}
                                role="tabpanel"
                                className="mt-3 grid gap-3 sm:grid-cols-2"
                            >
                                {activeFoods.map((food) => {
                                    const selected = foodIds.includes(food.id);
                                    const rule = ruleFor(food);
                                    const categorySelected = selectedInCategory(
                                        food.category_id ?? -1,
                                    ).length;
                                    const unavailableByLimit =
                                        !selected &&
                                        foodIds.length >= foodLimit &&
                                        !(
                                            rule?.maximum_count === 1 &&
                                            categorySelected > 0
                                        );
                                    return (
                                        <article
                                            key={food.id}
                                            className={`rounded-xl border p-3 ${selected ? "border-emerald-700 bg-emerald-50" : "border-stone-200 bg-white"}`}
                                        >
                                            <div className="flex gap-3">
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setPreviewFood(food)
                                                    }
                                                    className="shrink-0 rounded-xl outline-offset-2 focus:outline focus:outline-2 focus:outline-emerald-800"
                                                    aria-label={`View ${food.name} image`}
                                                >
                                                    <FoodImage
                                                        compact
                                                        name={food.name}
                                                        src={food.thumbnail_url}
                                                    />
                                                    <span className="sr-only">
                                                        View image
                                                    </span>
                                                </button>
                                                <div className="min-w-0 flex-1">
                                                    <h3 className="font-semibold text-stone-900">
                                                        {food.name}
                                                    </h3>
                                                    <p className="text-xs text-stone-500">
                                                        {food.category_name}
                                                    </p>
                                                    {food.description && (
                                                        <p className="mt-1 text-sm text-stone-600">
                                                            {food.description}
                                                        </p>
                                                    )}
                                                    <button
                                                        type="button"
                                                        disabled={
                                                            unavailableByLimit
                                                        }
                                                        onClick={() =>
                                                            toggleFood(food.id)
                                                        }
                                                        className={`mt-3 inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold ${selected ? "bg-emerald-800 text-white" : "border border-emerald-800 text-emerald-900"} disabled:cursor-not-allowed disabled:opacity-50`}
                                                    >
                                                        {selected && (
                                                            <Check
                                                                size={16}
                                                                aria-hidden="true"
                                                            />
                                                        )}
                                                        {selected
                                                            ? "Selected"
                                                            : rule?.maximum_count ===
                                                                    1 &&
                                                                categorySelected >
                                                                    0
                                                              ? "Replace selection"
                                                              : "Select food"}
                                                    </button>
                                                </div>
                                            </div>
                                        </article>
                                    );
                                })}
                            </div>
                        </>
                    )}
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
            {saved && (
                <div
                    role="status"
                    className="mt-5 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-950"
                >
                    {isMain
                        ? "Your Main Catering package is saved. You may now add optional services or continue to event details."
                        : "This optional service is saved to your inquiry."}
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
                    onClick={() =>
                        hasOtherMain
                            ? setConfirmReplacement(true)
                            : send(selectionUrl ?? previewUrl, "selection")
                    }
                    disabled={submitting !== null}
                    className="inline-flex items-center justify-center gap-2 rounded-xl bg-amber-600 px-5 py-3 font-semibold text-white hover:bg-amber-700"
                >
                    {submitting === "selection" ? (
                        <LoaderCircle className="animate-spin" size={17} />
                    ) : (
                        <Plus size={17} />
                    )}{" "}
                    {selectionLabel ??
                        (isMain
                            ? "Save Main Catering choice"
                            : "Add to inquiry")}
                </button>
            </div>
            {saved &&
                (onConfigurationSaved ? (
                    <p className="mt-4 text-sm font-semibold text-emerald-800">
                        Proposed configuration validated. Submit the change
                        request when you are ready.
                    </p>
                ) : (
                    <div className="mt-4 flex flex-wrap gap-4 text-sm font-semibold">
                        <Link
                            href={isMain ? "/add-on-services" : "/my-selection"}
                            className="text-emerald-800 underline"
                        >
                            {isMain
                                ? "Choose optional add-ons"
                                : "Review your inquiry"}
                        </Link>
                        {isMain && (
                            <Link
                                href="/my-selection"
                                className="text-emerald-800 underline"
                            >
                                Continue to event details
                            </Link>
                        )}
                    </div>
                ))}
            <ConfirmDialog
                open={confirmReplacement}
                onClose={() => setConfirmReplacement(false)}
                title="Replace your Main Catering package?"
                description="Your previous Main Catering food choices and guest quantity will be replaced. Optional add-ons remain in this inquiry and will be rechecked before submission."
                confirmLabel="Replace package"
                destructive
                onConfirm={() => {
                    setConfirmReplacement(false);
                    void send(selectionUrl ?? previewUrl, "selection", true);
                }}
            />
            {previewFood && (
                <FoodImagePreview
                    open
                    onClose={() => setPreviewFood(null)}
                    name={previewFood.name}
                    category={previewFood.category_name}
                    imageUrl={previewFood.image_url}
                    thumbnailUrl={previewFood.thumbnail_url}
                />
            )}
        </section>
    );
}
