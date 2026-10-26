import type { PackageVersion } from "../types";
import type { FoodCategory } from "../../catalog/types";
export type MenuFood = {
    id: number;
    name: string;
    category_id: number;
    thumbnail_url?: string | null;
    pivot?: {
        is_fixed: boolean;
        always_include?: boolean;
        included_quantity?: string | null;
        included_unit?: string | null;
        price_adjustment_minor?: string;
    };
};
export type MenuCategory = FoodCategory;
export type MenuVersion = PackageVersion & {
    food_items: MenuFood[];
    required_categories: (MenuCategory & {
        pivot?: {
            minimum_count?: number;
            maximum_count?: number;
            maximum_count_explicit?: boolean;
        };
    })[];
};
