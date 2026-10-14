export type FoodCategory = {
    id: number;
    code: string;
    name: string;
    sort_order: number;
    active: boolean;
    dishes_count?: number;
};
export type Collection = {
    id: number;
    code: string;
    name: string;
    is_addon: boolean;
    behavior_type:
        | "main_package"
        | "package"
        | "packed_meal"
        | "direct_sale"
        | "fixed_bundle";
    archived_at: string | null;
    description: string | null;
    sort_order: number;
    active: boolean;
    pivot?: { active: boolean };
    order_default?: CollectionOrderingDefault;
    products_count?: number;
    listings_count?: number;
};
export type CollectionOrderingDefault = {
    family_id: number;
    minimum_quantity: number;
    quantity_step: number;
    maximum_quantity: number | null;
    enforce_minimum_floor: boolean;
};
export type ServingOption = {
    id: number;
    offering_id: number;
    option_code: string;
    label: string;
    unit: string;
    quantity: string;
    serving_pax: number | null;
    serving_claim: string | null;
    active: boolean;
};
export type Offering = {
    id: number;
    dish_id: number;
    family_id: number;
    code: string;
    name: string;
    description: string | null;
    active: boolean;
    family?: Collection;
    options?: ServingOption[];
};
export type FoodItem = {
    image_url?: string | null;
    thumbnail_url?: string | null;
    id: number;
    code: string;
    name: string;
    description: string | null;
    category_id: number;
    active: boolean;
    category?: FoodCategory;
    eligible_families?: Collection[];
    offerings?: Offering[];
    offerings_count?: number;
};
