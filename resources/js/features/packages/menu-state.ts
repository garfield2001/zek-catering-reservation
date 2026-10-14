import type { MenuFood, MenuVersion } from "./components/package-menu-form";
import type { FoodDetail } from "./components/menu-food-details";
import { minorToPesoInput } from "./money";

export type MenuData = {
    total_selection_count: number;
    food_item_ids: number[];
    required_category_ids: number[];
    food_details: Record<number, FoodDetail>;
};
export const emptyFoodDetail = (): FoodDetail => ({
    is_fixed: false,
    included_quantity: "",
    included_unit: "",
    price_adjustment_pesos: "0",
});
export function initialMenu(version?: MenuVersion | null): MenuData {
    return {
        total_selection_count: version?.total_selection_count ?? 1,
        food_item_ids: version?.food_items.map((f) => f.id) ?? [],
        required_category_ids:
            version?.required_categories.map((c) => c.id) ?? [],
        food_details: Object.fromEntries(
            (version?.food_items ?? []).map((f) => [
                f.id,
                {
                    is_fixed: !!f.pivot?.always_include,
                    included_quantity: f.pivot?.included_quantity ?? "",
                    included_unit: f.pivot?.included_unit ?? "",
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
    const fixed = allowed.filter(
        (f) =>
            bundle ||
            data.food_details[f.id]?.is_fixed ||
            (data.required_category_ids.includes(f.category_id) &&
                allowed.filter((a) => a.category_id === f.category_id)
                    .length === 1),
    );
    const requiredChoices = data.required_category_ids.filter(
        (id) => !fixed.some((f) => f.category_id === id),
    ).length;
    const total = bundle ? allowed.length : data.total_selection_count;
    const impossible =
        total < 1 ||
        !Number.isInteger(total) ||
        allowed.length !== data.food_item_ids.length ||
        allowed.length < total ||
        fixed.length + requiredChoices > total ||
        data.required_category_ids.some(
            (id) => !allowed.some((f) => f.category_id === id),
        );
    return { allowed, fixed, requiredChoices, total, impossible };
}
