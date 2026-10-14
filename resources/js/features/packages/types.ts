import type { Collection } from "../catalog/types";

export type PackageProduct = {
    current_version_id?: number | null;
    id: number;
    family_id: number;
    code: string;
    name: string;
    description: string | null;
    sort_order: number;
    active: boolean;
    archived_at?: string | null;
    family?: Collection;
    versions?: PackageVersion[];
    versions_count?: number;
};
export type PackageVersion = {
    estimated_capacity?: number | null;
    id: number;
    package_product_id: number;
    family_id: number;
    version_number: number;
    status: "draft" | "published" | "abandoned";
    draft_revision: number;
    base_price_minor: string;
    price_basis: "per_pax" | "per_pack" | "per_set";
    selection_policy: "uniform" | "split";
    minimum_quantity: number;
    quantity_step: number;
    maximum_quantity: number | null;
    total_selection_count?: number | null;
    groups?: SelectionGroup[];
    published_snapshot?: PublishedPackageSnapshot | null;
};
export type PublishedPackageSnapshot = {
    schema: number;
    collection: {
        name: string;
        description: string | null;
        behavior_type: string;
    };
    product: { name: string; description: string | null };
    package: { name: string; description: string | null };
    version: {
        estimated_capacity?: number | null;
        number: number;
        base_price_minor: string;
        price_basis: string;
        minimum_quantity: number;
        quantity_step: number;
        maximum_quantity: number | null;
        selection_policy: string;
    };
    menu?: {
        total_selection_count: number | null;
        food_items: {
            id: number;
            name: string;
            is_fixed: boolean;
            always_include?: boolean;
            category_name?: string;
            included_quantity?: string | null;
            included_unit?: string | null;
            price_adjustment_minor?: string;
        }[];
        required_categories: { id: number; name: string }[];
    } | null;
    groups: {
        group_code: string;
        name: string;
        mode: string;
        minimum_count: number;
        maximum_count: number;
        inclusion_count: string;
        options: {
            offering_option_id: number;
            is_default: boolean;
            price_adjustment_minor: string;
            food_item: { name: string; description: string | null };
            food_category: { name: string };
            serving_option: {
                label: string;
                unit: string;
                quantity: string;
                serving_pax?: number | null;
                serving_claim?: string | null;
            };
        }[];
        category_rules: {
            category_name: string;
            minimum_count: number;
            maximum_count: number;
        }[];
        food_item_rules: { food_item_name: string; effect: string }[];
    }[];
};
export type GroupAllowedOption = {
    id: number;
    group_id: number;
    offering_option_id: number;
    is_default: boolean;
    price_adjustment_minor: string;
    offering_option?: {
        id: number;
        label: string;
        unit: string;
        quantity: string;
        offering?: { name: string; dish?: { name: string } };
    };
};
export type CategoryRule = {
    category_id: number;
    minimum_count: number;
    maximum_count: number;
};
export type FoodItemRule = { dish_id: number; effect: "allow" | "deny" };
export type SelectionGroup = {
    id: number;
    package_version_id: number;
    group_code: string;
    name: string;
    mode: "fixed" | "select_one" | "select_many";
    slot_kind:
        "item" | "rice" | "drink" | "dish" | "lechon" | "tray" | "bundle_item";
    minimum_count: number;
    maximum_count: number;
    unique_dishes: boolean;
    category_mode: "free" | "restricted";
    inclusion_count: string;
    sort_order: number;
    options?: GroupAllowedOption[];
    category_rules?: CategoryRule[];
    dish_rules?: FoodItemRule[];
};
export type AvailableServingOption = {
    id: number;
    label: string;
    unit: string;
    quantity: string;
    serving_pax: number | null;
    dish_id: number;
    dish_name: string;
    category_id: number;
    category_name: string;
    offering_name: string;
};
export type Preview = {
    status: "draft_preview";
    quantity: number;
    price_basis: string;
    currency: string;
    effective_quantity_rules: {
        minimum_quantity: number;
        quantity_step: number;
        maximum_quantity: number | null;
    };
    groups: {
        quantity: number;
        unit_base_minor: string;
        base_subtotal_minor: string;
        adjustments: {
            group_code: string;
            offering_option_id: number;
            unit_amount_minor: string;
            line_amount_minor: string;
        }[];
        line_total_minor: string;
    }[];
    total_minor: string;
};
