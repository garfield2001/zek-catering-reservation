import type { CategoryRule, DishRule, PackageGroup } from "../types";

export type GroupOptionInput = {
    offering_option_id: number;
    is_default: boolean;
    price_adjustment_minor: string;
};
export type GroupPayload = {
    group_code: string;
    name: string;
    mode: PackageGroup["mode"];
    slot_kind: PackageGroup["slot_kind"];
    minimum_count: number;
    maximum_count: number;
    unique_dishes: boolean;
    category_mode: PackageGroup["category_mode"];
    inclusion_count: string;
    sort_order: number;
    options: GroupOptionInput[];
    category_rules: CategoryRule[];
    dish_rules: DishRule[];
};
