import type {
    AvailableServingOption,
    CategoryRule,
    FoodItemRule,
    SelectionGroup,
} from "../types";

export function SelectionGroupRuleEditors({
    categoryMode,
    categoryRules,
    dishRules,
    categories,
    options,
    maximumCount,
    error,
    onMode,
    onCategories,
    onDishes,
}: {
    categoryMode: SelectionGroup["category_mode"];
    categoryRules: CategoryRule[];
    dishRules: FoodItemRule[];
    categories: { id: number; name: string }[];
    options: AvailableServingOption[];
    maximumCount: number;
    error?: string;
    onMode: (mode: SelectionGroup["category_mode"]) => void;
    onCategories: (rules: CategoryRule[]) => void;
    onDishes: (rules: FoodItemRule[]) => void;
}) {
    const dishes = [
        ...new Map(
            options.map((option) => [option.dish_id, option.dish_name]),
        ).entries(),
    ];
    const changeCategory = (id: number, changes: Partial<CategoryRule>) =>
        onCategories(
            categoryRules.map((rule) =>
                rule.category_id === id ? { ...rule, ...changes } : rule,
            ),
        );
    return (
        <>
            <section className="rounded-xl border border-stone-200 bg-white p-5">
                <h2 className="font-semibold">Food category rules</h2>
                <p className="text-sm text-stone-600">
                    Free mode permits any allowed food item; restricted mode
                    whitelists the categories below and applies quotas.
                </p>
                <select
                    className="mt-3"
                    value={categoryMode}
                    onChange={(event) => {
                        const mode = event.target
                            .value as SelectionGroup["category_mode"];
                        onMode(mode);
                    }}
                >
                    <option value="free">Free choice, no quotas</option>
                    <option value="restricted">
                        Restricted categories and quotas
                    </option>
                </select>
                {categoryMode === "restricted" && (
                    <div className="mt-4 space-y-2">
                        {categories.map((category) => {
                            const rule = categoryRules.find(
                                (item) => item.category_id === category.id,
                            );
                            return (
                                <div
                                    key={category.id}
                                    className="flex flex-wrap items-center gap-3 text-sm"
                                >
                                    <label>
                                        <input
                                            type="checkbox"
                                            checked={!!rule}
                                            onChange={(event) =>
                                                onCategories(
                                                    event.target.checked
                                                        ? [
                                                              ...categoryRules,
                                                              {
                                                                  category_id:
                                                                      category.id,
                                                                  minimum_count: 0,
                                                                  maximum_count:
                                                                      maximumCount,
                                                              },
                                                          ]
                                                        : categoryRules.filter(
                                                              (item) =>
                                                                  item.category_id !==
                                                                  category.id,
                                                          ),
                                                )
                                            }
                                        />{" "}
                                        {category.name}
                                    </label>
                                    {rule && (
                                        <>
                                            <label>
                                                Minimum{" "}
                                                <input
                                                    className="w-20"
                                                    type="number"
                                                    min="0"
                                                    value={rule.minimum_count}
                                                    onChange={(event) =>
                                                        changeCategory(
                                                            category.id,
                                                            {
                                                                minimum_count:
                                                                    Number(
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    ),
                                                            },
                                                        )
                                                    }
                                                />
                                            </label>
                                            <label>
                                                Maximum{" "}
                                                <input
                                                    className="w-20"
                                                    type="number"
                                                    min="1"
                                                    value={rule.maximum_count}
                                                    onChange={(event) =>
                                                        changeCategory(
                                                            category.id,
                                                            {
                                                                maximum_count:
                                                                    Number(
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    ),
                                                            },
                                                        )
                                                    }
                                                />
                                            </label>
                                        </>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                )}
                {error && (
                    <p role="alert" className="text-sm text-red-700">
                        {error}
                    </p>
                )}
            </section>
            <section className="rounded-xl border border-stone-200 bg-white p-5">
                <h2 className="font-semibold">
                    Food item allow/deny exceptions
                </h2>
                <div className="mt-3 grid gap-2 sm:grid-cols-2">
                    {dishes.map(([id, name]) => (
                        <label key={id} className="text-sm">
                            {name}
                            <select
                                className="mt-1"
                                value={
                                    dishRules.find(
                                        (rule) => rule.dish_id === id,
                                    )?.effect ?? "any"
                                }
                                onChange={(event) =>
                                    onDishes([
                                        ...dishRules.filter(
                                            (rule) => rule.dish_id !== id,
                                        ),
                                        ...(event.target.value === "any"
                                            ? []
                                            : [
                                                  {
                                                      dish_id: id,
                                                      effect: event.target
                                                          .value as
                                                          "allow" | "deny",
                                                  },
                                              ]),
                                    ])
                                }
                            >
                                <option value="any">No exception</option>
                                <option value="allow">Allow-list</option>
                                <option value="deny">Deny</option>
                            </select>
                        </label>
                    ))}
                </div>
            </section>
        </>
    );
}
