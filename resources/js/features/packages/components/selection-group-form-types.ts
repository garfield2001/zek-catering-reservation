import type { CategoryRule, FoodItemRule, SelectionGroup } from "../types";

export type GroupAllowedOptionInput = {
    offering_option_id: number;
    is_default: boolean;
    price_adjustment_pesos: string;
};
export type SelectionGroupPayload = {
    draft_revision: number;
    name: string;
    mode: SelectionGroup["mode"];
    slot_kind: SelectionGroup["slot_kind"];
    minimum_count: number;
    maximum_count: number;
    unique_dishes: boolean;
    category_mode: SelectionGroup["category_mode"];
    inclusion_count: string;
    options: GroupAllowedOptionInput[];
    category_rules: CategoryRule[];
    dish_rules: FoodItemRule[];
};
