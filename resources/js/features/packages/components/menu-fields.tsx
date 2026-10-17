import { Field } from "../../../components/ui/field";
import { FoodImage } from "../../../components/ui/food-image";
import type { MenuFood, MenuCategory } from "./package-menu-form";
import type { MenuData } from "../menu-state";
import { emptyFoodDetail, menuSummary } from "../menu-state";
import { MenuFoodDetails } from "./menu-food-details";
import type { ReactNode } from "react";

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
    const { allowed, fixed, total, requiredChoices, impossible } = menuSummary(
        data,
        foods,
        bundle,
    );
    const toggleFood = (id: number, checked: boolean) =>
        onChange({
            food_item_ids: checked
                ? [...new Set([...data.food_item_ids, id])]
                : data.food_item_ids.filter((n) => n !== id),
        });
    const detail = (id: number) => data.food_details[id] ?? emptyFoodDetail();
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
                        onChange={(e) =>
                            onChange({
                                total_selection_count: Number(e.target.value),
                            })
                        }
                    />
                </Field>
            )}
            {onAddFood && (
                <button
                    type="button"
                    className="text-sm text-emerald-800 underline"
                    onClick={() => onAddFood()}
                >
                    Add Food or New Category
                </button>
            )}
            {allowed.length !== data.food_item_ids.length && (
                <p className="rounded bg-amber-50 p-3 text-sm">
                    Some saved foods are no longer available.{" "}
                    <button
                        type="button"
                        className="underline"
                        onClick={() =>
                            onChange({
                                food_item_ids: allowed.map((f) => f.id),
                            })
                        }
                    >
                        Remove unavailable foods
                    </button>
                </p>
            )}
            <div className="grid gap-4 lg:grid-cols-2">
                {categories.map((category) => {
                    const choices = foods.filter(
                        (f) => f.category_id === category.id,
                    );
                    const selected = choices.filter((f) =>
                        data.food_item_ids.includes(f.id),
                    ).length;
                    return (
                        <fieldset
                            key={category.id}
                            className="rounded-xl border border-stone-200 p-4"
                        >
                            <legend className="px-1 font-semibold">
                                {category.name}{" "}
                                <span className="text-xs font-normal text-stone-500">
                                    {selected}/{choices.length} allowed
                                </span>
                            </legend>
                            <div className="mb-3 flex flex-wrap gap-4 text-xs text-emerald-800">
                                <button
                                    type="button"
                                    onClick={() =>
                                        onChange({
                                            food_item_ids: [
                                                ...new Set([
                                                    ...data.food_item_ids,
                                                    ...choices.map((f) => f.id),
                                                ]),
                                            ],
                                        })
                                    }
                                >
                                    Select All
                                </button>
                                <button
                                    type="button"
                                    onClick={() =>
                                        onChange({
                                            food_item_ids:
                                                data.food_item_ids.filter(
                                                    (id) =>
                                                        !choices.some(
                                                            (f) => f.id === id,
                                                        ),
                                                ),
                                        })
                                    }
                                >
                                    Clear All
                                </button>
                                {onAddFood && (
                                    <button
                                        type="button"
                                        onClick={() => onAddFood(category.id)}
                                    >
                                        Add Food
                                    </button>
                                )}
                            </div>
                            {!bundle && (
                                <label className="mb-3 flex gap-2 border-b pb-3 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={data.required_category_ids.includes(
                                            category.id,
                                        )}
                                        onChange={(e) =>
                                            onChange({
                                                required_category_ids: e.target
                                                    .checked
                                                    ? [
                                                          ...data.required_category_ids,
                                                          category.id,
                                                      ]
                                                    : data.required_category_ids.filter(
                                                          (id) =>
                                                              id !==
                                                              category.id,
                                                      ),
                                            })
                                        }
                                    />
                                    Require at least one
                                </label>
                            )}
                            <div className="space-y-3">
                                {choices.map((food) => {
                                    const selected =
                                        data.food_item_ids.includes(food.id);
                                    const automatic =
                                        selected &&
                                        data.required_category_ids.includes(
                                            category.id,
                                        ) &&
                                        allowed.filter(
                                            (f) =>
                                                f.category_id === category.id,
                                        ).length === 1;
                                    return (
                                        <div
                                            key={food.id}
                                            className={`rounded-lg border p-3 ${selected ? "border-emerald-700 bg-emerald-50/50" : "border-stone-200"}`}
                                        >
                                            <label className="flex cursor-pointer items-center gap-3 text-sm">
                                                <input
                                                    type="checkbox"
                                                    aria-label={food.name}
                                                    checked={selected}
                                                    onChange={(e) =>
                                                        toggleFood(
                                                            food.id,
                                                            e.target.checked,
                                                        )
                                                    }
                                                />
                                                <FoodImage
                                                    name={food.name}
                                                    src={food.thumbnail_url}
                                                    compact
                                                />
                                                <span className="font-medium">
                                                    {food.name}
                                                </span>
                                            </label>
                                            {selected && !bundle && (
                                                <label className="mt-2 flex gap-2 text-xs">
                                                    <input
                                                        type="checkbox"
                                                        checked={
                                                            detail(food.id)
                                                                .is_fixed
                                                        }
                                                        onChange={(e) =>
                                                            onChange({
                                                                food_details: {
                                                                    ...data.food_details,
                                                                    [food.id]: {
                                                                        ...detail(
                                                                            food.id,
                                                                        ),
                                                                        is_fixed:
                                                                            e
                                                                                .target
                                                                                .checked,
                                                                    },
                                                                },
                                                            })
                                                        }
                                                    />
                                                    Always include {food.name}
                                                </label>
                                            )}
                                            {automatic && (
                                                <p className="mt-1 text-xs text-emerald-800">
                                                    Automatically included: the
                                                    only allowed food in this
                                                    required category.
                                                </p>
                                            )}
                                            {selected &&
                                                !main &&
                                                renderFoodConfiguration?.(
                                                    food,
                                                    fixed.some(
                                                        (item) =>
                                                            item.id === food.id,
                                                    ),
                                                )}
                                            {selected &&
                                                !main &&
                                                !renderFoodConfiguration && (
                                                    <MenuFoodDetails
                                                        food={food}
                                                        value={detail(food.id)}
                                                        onChange={(values) =>
                                                            onChange({
                                                                food_details: {
                                                                    ...data.food_details,
                                                                    [food.id]: {
                                                                        ...detail(
                                                                            food.id,
                                                                        ),
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
                                        </div>
                                    );
                                })}
                            </div>
                            {!choices.length && (
                                <p className="text-sm text-stone-500">
                                    No available foods. Add a food here or
                                    enable the food and its category in Food
                                    Catalog.
                                </p>
                            )}
                        </fieldset>
                    );
                })}
            </div>
            <aside
                aria-label="Menu summary"
                aria-live="polite"
                className="rounded-xl bg-emerald-50 p-4 text-sm"
            >
                <p>
                    <strong>Fixed inclusions:</strong>{" "}
                    {fixed.map((f) => f.name).join(", ") || "None"}
                </p>
                <p>
                    {requiredChoices} required category choices. Exactly {total}{" "}
                    distinct foods, including fixed foods.
                </p>
                {main && (
                    <p>No food surcharges or per-food serving quantities.</p>
                )}
                {impossible && (
                    <p role="status" className="mt-2 text-amber-900">
                        Check your menu: every required category needs an
                        allowed food, and fixed foods plus required choices must
                        fit the total. Allow at least {total} available foods.
                    </p>
                )}
            </aside>
        </div>
    );
}
