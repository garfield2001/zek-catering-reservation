import { useMemo, useState, type ReactNode } from "react";
import { Field } from "../../../components/ui/field";
import { FoodImage } from "../../../components/ui/food-image";
import { SearchableSelect } from "../../../components/ui/searchable-select";
import type { MenuFood, MenuCategory } from "./package-menu-form";
import type { MenuData } from "../menu-state";
import { emptyFoodDetail, menuSummary } from "../menu-state";
import { MenuFoodDetails } from "./menu-food-details";

/** Keeps catalog discovery separate from advanced per-food configuration. */
export function MenuFields({
    data,
    onChange,
    foods,
    categories,
    main,
    bundle,
    packed,
    canPrice,
    onAddFood,
    renderFoodConfiguration,
}: {
    data: MenuData;
    onChange: (values: Partial<MenuData>) => void;
    foods: MenuFood[];
    categories: MenuCategory[];
    main: boolean;
    bundle: boolean;
    packed: boolean;
    canPrice: boolean;
    onAddFood?: (category?: number) => void;
    renderFoodConfiguration?: (food: MenuFood, fixed: boolean) => ReactNode;
}) {
    const [search, setSearch] = useState("");
    const [categoryId, setCategoryId] = useState<number | "all">("all");
    const { allowed, effectiveFixed, choiceRules, total, impossible } =
        menuSummary(data, foods, bundle);
    const visible = useMemo(() => {
        const term = search.trim().toLocaleLowerCase();
        return foods.filter(
            (food) =>
                (categoryId === "all" || food.category_id === categoryId) &&
                (!term || food.name.toLocaleLowerCase().includes(term)),
        );
    }, [categoryId, foods, search]);
    const selected = new Set(data.food_item_ids);
    const category =
        categoryId === "all"
            ? null
            : (categories.find((item) => item.id === categoryId) ?? null);
    const detail = (id: number) => data.food_details[id] ?? emptyFoodDetail();
    const toggleFood = (id: number, checked: boolean) =>
        onChange({
            food_item_ids: checked
                ? [...new Set([...data.food_item_ids, id])]
                : data.food_item_ids.filter((current) => current !== id),
        });
    const toggleVisible = (checked: boolean) => {
        const visibleIds = new Set(visible.map((food) => food.id));
        onChange({
            food_item_ids: checked
                ? [...new Set([...data.food_item_ids, ...visibleIds])]
                : data.food_item_ids.filter((id) => !visibleIds.has(id)),
        });
    };
    const categoryName = (id: number) =>
        categories.find((item) => item.id === id)?.name ?? "Uncategorized";
    const setChoiceRules = (
        rules: {
            category_id: number;
            minimum_count: number;
            maximum_count: number;
            maximum_count_explicit: boolean;
        }[],
    ) =>
        onChange({
            category_choice_rules: rules,
            required_category_ids: rules.map((rule) => rule.category_id),
        });

    return (
        <div className="space-y-5">
            {!bundle && (
                <Field
                    id="selection-total"
                    label="Total included food selections"
                >
                    <input
                        id="selection-total"
                        required
                        type="number"
                        min="1"
                        value={data.total_selection_count}
                        onChange={(event) =>
                            onChange({
                                total_selection_count: Number(
                                    event.target.value,
                                ),
                            })
                        }
                    />
                </Field>
            )}
            <section
                aria-label="Find foods"
                className="rounded-2xl border bg-slate-50 p-4"
            >
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h3 className="font-semibold">Find foods</h3>
                        <p className="text-sm text-slate-600">
                            Search eligible foods, select them, then customize
                            only the foods that need an exception.
                        </p>
                    </div>
                    {onAddFood && (
                        <button
                            type="button"
                            className="text-sm font-medium text-emerald-800 underline"
                            onClick={() => onAddFood(category?.id)}
                        >
                            Add Food or New Category
                        </button>
                    )}
                </div>
                <div className="mt-4 grid gap-3 sm:grid-cols-2">
                    <label className="text-sm font-medium">
                        Category
                        <SearchableSelect
                            value={categoryId}
                            placeholder="All eligible foods"
                            onChange={(value) => {
                                setCategoryId(
                                    value === "all" ? "all" : Number(value),
                                );
                                setSearch("");
                            }}
                            options={[
                                { value: "all", label: "All eligible foods" },
                                ...categories.map((item) => ({
                                    value: item.id,
                                    label: `${item.name} (${foods.filter((food) => food.category_id === item.id).length})`,
                                })),
                            ]}
                        />
                    </label>
                    <label className="text-sm font-medium">
                        Search eligible foods
                        <input
                            className="mt-1"
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Type a food name"
                        />
                    </label>
                </div>
                <div className="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm">
                    <p aria-live="polite" className="text-slate-600">
                        Showing {visible.length} eligible food
                        {visible.length === 1 ? "" : "s"}
                        {search ? " matching this search" : ""}.
                    </p>
                    {!!visible.length && (
                        <div className="flex gap-3 text-emerald-800">
                            <button
                                type="button"
                                onClick={() => toggleVisible(true)}
                            >
                                Select all shown
                            </button>
                            <button
                                type="button"
                                onClick={() => toggleVisible(false)}
                            >
                                Clear shown
                            </button>
                        </div>
                    )}
                </div>
                <div className="mt-3 grid max-h-[22rem] gap-2 overflow-y-auto pr-1 sm:grid-cols-2">
                    {visible.map((food) => (
                        <label
                            key={food.id}
                            className={`flex cursor-pointer items-center gap-3 rounded-xl border p-3 text-sm ${selected.has(food.id) ? "border-emerald-700 bg-emerald-50" : "border-slate-200 bg-white"}`}
                        >
                            <input
                                type="checkbox"
                                aria-label={food.name}
                                checked={selected.has(food.id)}
                                onChange={(event) =>
                                    toggleFood(food.id, event.target.checked)
                                }
                            />
                            <FoodImage
                                name={food.name}
                                src={food.thumbnail_url}
                                compact
                            />
                            <span className="min-w-0">
                                <span className="block truncate font-medium">
                                    {food.name}
                                </span>
                                <span className="block truncate text-xs text-slate-500">
                                    {categoryName(food.category_id)}
                                </span>
                            </span>
                        </label>
                    ))}
                    {!visible.length && (
                        <p className="rounded-lg border border-dashed bg-white p-4 text-sm text-slate-600 sm:col-span-2">
                            No eligible foods match. Try another category or
                            search, or add a Food Item to the shared catalog.
                        </p>
                    )}
                </div>
            </section>
            {allowed.length !== data.food_item_ids.length && (
                <p className="rounded bg-amber-50 p-3 text-sm">
                    Some saved foods are no longer available.{" "}
                    <button
                        type="button"
                        className="underline"
                        onClick={() =>
                            onChange({
                                food_item_ids: allowed.map((food) => food.id),
                            })
                        }
                    >
                        Remove unavailable foods
                    </button>
                </p>
            )}
            {!bundle && (
                <section
                    aria-label="Customer Choice Groups"
                    className="space-y-4 rounded-2xl border bg-white p-4"
                >
                    <div>
                        <h3 className="font-semibold">
                            Customer Choice Groups
                        </h3>
                        <p className="text-sm text-slate-600">
                            Set how many different foods customers must or may
                            choose from each Food Category. Foods marked Always
                            Included are not customer choices.
                        </p>
                    </div>
                    {categories
                        .filter((item) =>
                            allowed.some(
                                (food) => food.category_id === item.id,
                            ),
                        )
                        .map((item) => {
                            const rule = choiceRules.find(
                                (current) => current.category_id === item.id,
                            );
                            const options = allowed.filter(
                                (food) =>
                                    food.category_id === item.id &&
                                    !detail(food.id).is_fixed,
                            );
                            return (
                                <article
                                    key={item.id}
                                    className="rounded-xl border border-slate-200 p-4"
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <h4 className="font-medium">
                                                {item.name}
                                            </h4>
                                            <p className="text-xs text-slate-500">
                                                {options.length} available
                                                option
                                                {options.length === 1
                                                    ? ""
                                                    : "s"}
                                            </p>
                                        </div>
                                        {rule ? (
                                            <button
                                                type="button"
                                                className="text-sm text-rose-700 underline"
                                                onClick={() =>
                                                    setChoiceRules(
                                                        choiceRules.filter(
                                                            (current) =>
                                                                current.category_id !==
                                                                item.id,
                                                        ),
                                                    )
                                                }
                                            >
                                                Remove customer choice group
                                            </button>
                                        ) : (
                                            <button
                                                type="button"
                                                disabled={options.length === 0}
                                                className="text-sm font-medium text-emerald-800 underline disabled:text-slate-400"
                                                onClick={() =>
                                                    setChoiceRules([
                                                        ...choiceRules,
                                                        {
                                                            category_id:
                                                                item.id,
                                                            minimum_count: 1,
                                                            maximum_count: 1,
                                                            maximum_count_explicit: true,
                                                        },
                                                    ])
                                                }
                                            >
                                                Require customer choice
                                            </button>
                                        )}
                                    </div>
                                    {rule && (
                                        <div className="mt-4 grid gap-3 sm:grid-cols-2">
                                            <label className="text-sm font-medium">
                                                Minimum choices
                                                <input
                                                    className="mt-1"
                                                    type="number"
                                                    min="0"
                                                    max={options.length}
                                                    value={rule.minimum_count}
                                                    onChange={(event) =>
                                                        setChoiceRules(
                                                            choiceRules.map(
                                                                (current) =>
                                                                    current.category_id ===
                                                                    item.id
                                                                        ? {
                                                                              ...current,
                                                                              minimum_count:
                                                                                  Number(
                                                                                      event
                                                                                          .target
                                                                                          .value,
                                                                                  ),
                                                                          }
                                                                        : current,
                                                            ),
                                                        )
                                                    }
                                                />
                                            </label>
                                            <label className="text-sm font-medium">
                                                Maximum choices
                                                <input
                                                    className="mt-1"
                                                    type="number"
                                                    min="1"
                                                    max={options.length}
                                                    value={rule.maximum_count}
                                                    onChange={(event) =>
                                                        setChoiceRules(
                                                            choiceRules.map(
                                                                (current) =>
                                                                    current.category_id ===
                                                                    item.id
                                                                        ? {
                                                                              ...current,
                                                                              maximum_count:
                                                                                  Number(
                                                                                      event
                                                                                          .target
                                                                                          .value,
                                                                                  ),
                                                                              maximum_count_explicit: true,
                                                                          }
                                                                        : current,
                                                            ),
                                                        )
                                                    }
                                                />
                                            </label>
                                            <p className="text-sm text-slate-600 sm:col-span-2">
                                                Customers choose{" "}
                                                {rule.minimum_count ===
                                                rule.maximum_count
                                                    ? `exactly ${rule.minimum_count}`
                                                    : `${rule.minimum_count} to ${rule.maximum_count}`}{" "}
                                                from {item.name}.
                                            </p>
                                            {rule.minimum_count > 0 &&
                                                options.length === 1 && (
                                                    <p className="text-sm text-emerald-800 sm:col-span-2">
                                                        {options[0].name} is
                                                        automatically included
                                                        when this package is
                                                        saved because it is the
                                                        only eligible choice.
                                                    </p>
                                                )}
                                        </div>
                                    )}
                                </article>
                            );
                        })}
                    {!allowed.length && (
                        <p className="text-sm text-slate-600">
                            Select Food Items before adding customer choices.
                        </p>
                    )}
                </section>
            )}
            <section
                aria-label="Selected foods"
                className="rounded-2xl border bg-white p-4"
            >
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 className="font-semibold">
                            Selected foods — {allowed.length} item
                            {allowed.length === 1 ? "" : "s"}
                        </h3>
                        <p
                            aria-live="polite"
                            className="text-sm text-slate-600"
                        >
                            {bundle
                                ? `${allowed.length} included food${allowed.length === 1 ? "" : "s"}.`
                                : `${allowed.length} of ${total} selected. ${Math.max(0, total - allowed.length)} more required.`}
                        </p>
                    </div>
                </div>
                <div className="mt-4 space-y-3">
                    {allowed.map((food) => {
                        const isFixed = bundle || detail(food.id).is_fixed;
                        return (
                            <article
                                key={food.id}
                                className="rounded-xl border border-slate-200 p-3"
                            >
                                <div className="flex flex-wrap items-center gap-3">
                                    <FoodImage
                                        name={food.name}
                                        src={food.thumbnail_url}
                                        compact
                                    />
                                    <div className="min-w-0 flex-1">
                                        <p className="font-medium">
                                            {food.name}
                                        </p>
                                        <p className="text-xs text-slate-500">
                                            {categoryName(food.category_id)}
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        className="text-sm text-rose-700 underline"
                                        onClick={() =>
                                            toggleFood(food.id, false)
                                        }
                                    >
                                        Remove
                                    </button>
                                </div>
                                {!bundle && (
                                    <label className="mt-3 flex gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={detail(food.id).is_fixed}
                                            onChange={(event) =>
                                                onChange({
                                                    food_details: {
                                                        ...data.food_details,
                                                        [food.id]: {
                                                            ...detail(food.id),
                                                            is_fixed:
                                                                event.target
                                                                    .checked,
                                                        },
                                                    },
                                                })
                                            }
                                        />
                                        {`Always include ${food.name}`}
                                    </label>
                                )}
                                {!main &&
                                    renderFoodConfiguration?.(food, isFixed)}
                                {!main && !renderFoodConfiguration && (
                                    <MenuFoodDetails
                                        food={food}
                                        value={detail(food.id)}
                                        onChange={(values) =>
                                            onChange({
                                                food_details: {
                                                    ...data.food_details,
                                                    [food.id]: {
                                                        ...detail(food.id),
                                                        ...values,
                                                    },
                                                },
                                            })
                                        }
                                        bundle={bundle}
                                        packed={packed}
                                        canPrice={canPrice}
                                        showFixed={false}
                                    />
                                )}
                            </article>
                        );
                    })}
                    {!allowed.length && (
                        <p className="rounded-lg border border-dashed p-4 text-sm text-slate-600">
                            Select foods above to begin configuring this
                            package.
                        </p>
                    )}
                </div>
            </section>
            <aside
                aria-label="Menu summary"
                aria-live="polite"
                className="rounded-xl bg-emerald-50 p-4 text-sm"
            >
                <p>
                    <strong>Included items:</strong>{" "}
                    {effectiveFixed.map((food) => food.name).join(", ") ||
                        "None"}
                </p>
                <p>
                    {choiceRules.length
                        ? choiceRules
                              .map((rule) => {
                                  const name = categoryName(rule.category_id);
                                  return `${name}: ${rule.minimum_count === rule.maximum_count ? `exactly ${rule.minimum_count}` : `${rule.minimum_count} to ${rule.maximum_count}`}`;
                              })
                              .join(" · ")
                        : "No required customer choices"}
                    . Exactly {total} distinct foods, including included items.
                </p>
                {main && (
                    <p>No food surcharges or per-food serving quantities.</p>
                )}
                {impossible && (
                    <p role="status" className="mt-2 text-amber-900">
                        Check this menu: each Customer Choice Group needs enough
                        eligible options, its minimum cannot exceed its maximum,
                        and included foods plus required choices must fit the
                        total.
                    </p>
                )}
            </aside>
        </div>
    );
}
