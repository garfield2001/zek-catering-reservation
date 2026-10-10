export type Category = {
    id: number;
    code: string;
    name: string;
    sort_order: number;
    active: boolean;
    dishes_count?: number;
};
export type Family = {
    id: number;
    code: string;
    name: string;
    is_addon: boolean;
    description: string | null;
    sort_order: number;
    active: boolean;
    pivot?: { active: boolean };
    order_default?: OrderDefault;
};
export type OrderDefault = {
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
    family?: Family;
    options?: ServingOption[];
};
export type Dish = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    category_id: number;
    active: boolean;
    category?: Category;
    eligible_families?: Family[];
    offerings?: Offering[];
    offerings_count?: number;
};
