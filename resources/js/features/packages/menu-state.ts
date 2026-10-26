import type { MenuFood, MenuVersion } from "./components/package-menu-form";
import type { FoodDetail } from "./components/menu-food-details";
import { minorToPesoInput } from "./money";

export type MenuData = {
    total_selection_count: number;
    food_item_ids: number[];
    required_category_ids: number[];
    category_choice_rules: {
        category_id: number;
        minimum_count: number;
        maximum_count: number;
        maximum_count_explicit: boolean;
    }[];
    food_details: Record<number, FoodDetail>;
};
export const emptyFoodDetail = (): FoodDetail => ({
    is_fixed: false,
    included_quantity: "",
    included_unit: "",
    included_unit_id: "",
    price_adjustment_pesos: "0",
});
export function initialMenu(version?: MenuVersion | null): MenuData {
    return {
        total_selection_count: version?.total_selection_count ?? 1,
        food_item_ids: version?.food_items.map((f) => f.id) ?? [],
        required_category_ids:
            version?.required_categories.map((c) => c.id) ?? [],
        category_choice_rules:
            version?.required_categories.map((category) => ({
                category_id: category.id,
                minimum_count: category.pivot?.minimum_count ?? 1,
                maximum_count: category.pivot?.maximum_count ?? 1,
                maximum_count_explicit:
                    category.pivot?.maximum_count_explicit ?? false,
            })) ?? [],
        food_details: Object.fromEntries(
            (version?.food_items ?? []).map((f) => [
                f.id,
                {
                    is_fixed: !!f.pivot?.always_include,
                    included_quantity: f.pivot?.included_quantity ?? "",
                    included_unit: f.pivot?.included_unit ?? "",
                    included_unit_id: "",
                    price_adjustment_pesos: minorToPesoInput(
                        f.pivot?.price_adjustment_minor ?? "0",
                    ),
                },
            ]),
        ),
    };
}
export function menuSummary(data: MenuData, foods: MenuFood[], bundle = false) {
    const allowed = foods.filter((f) => data.food_item_ids.includes(f.id));
    const choiceRules = data.category_choice_rules.length
        ? data.category_choice_rules
        : data.required_category_ids.map((category_id) => ({
              category_id,
              minimum_count: 1,
              maximum_count: 1,
              maximum_count_explicit: false,
          }));
    const fixed = allowed.filter(
        (f) => bundle || data.food_details[f.id]?.is_fixed,
    );
    // Saving applies the matching backend invariant: a required category with
    // one eligible Food Item becomes an included item. Reflect that outcome in
    // the draft summary without changing the submitted rule or bypassing the
    // authoritative server-side promotion.
    const automaticallyIncluded = new Set(
        choiceRules.flatMap((rule) => {
            const matches = allowed.filter(
                (food) => food.category_id === rule.category_id,
            );

            return rule.minimum_count > 0 && matches.length === 1
                ? matches.map((food) => food.id)
                : [];
        }),
    );
    const effectiveFixed = allowed.filter(
        (food) =>
            bundle ||
            data.food_details[food.id]?.is_fixed ||
            automaticallyIncluded.has(food.id),
    );
    const requiredChoices = choiceRules.reduce(
        (count, rule) => count + rule.minimum_count,
        0,
    );
    const total = bundle ? allowed.length : data.total_selection_count;
    const impossible =
        total < 1 ||
        !Number.isInteger(total) ||
        allowed.length !== data.food_item_ids.length ||
        allowed.length < total ||
        fixed.length + requiredChoices > total ||
        choiceRules.some((rule) => {
            const choices = allowed.filter(
                (food) =>
                    food.category_id === rule.category_id &&
                    !data.food_details[food.id]?.is_fixed,
            );
            return (
                rule.minimum_count < 0 ||
                rule.minimum_count > rule.maximum_count ||
                choices.length < rule.maximum_count
            );
        });
    return {
        allowed,
        fixed,
        effectiveFixed,
        choiceRules,
        requiredChoices,
        total,
        impossible,
    };
}
