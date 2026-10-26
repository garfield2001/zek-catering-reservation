export type PublicFood = {
    id: number;
    category_id?: number;
    name: string;
    description?: string | null;
    category_name?: string | null;
    thumbnail_url?: string | null;
    is_fixed?: boolean;
    always_include?: boolean;
    price_adjustment_minor?: string;
};

export type PublicPackage = {
    id: number;
    name: string;
    description: string | null;
    family: { id: number; name: string; behavior_type: string };
    version_id: number;
    terms: {
        price_basis: "per_pax" | "per_pack" | "per_set";
        base_price_minor: string;
        minimum_quantity: number;
        quantity_step: number;
        maximum_quantity: number | null;
        total_selection_count?: number | null;
        estimated_capacity?: number | null;
        selection_policy?: "uniform" | "split";
    };
    menu: {
        total_selection_count: number | null;
        food_items: PublicFood[];
        required_categories: { id: number; name: string }[];
        category_choice_rules?: {
            id: number;
            name: string;
            minimum_count: number;
            maximum_count: number;
        }[];
    } | null;
    groups: PublicGroup[];
    presentation?: { format?: string } | null;
};

export type PublicGroup = {
    id: number;
    group_code: string;
    name: string;
    mode: "fixed" | "select_one" | "select_many";
    minimum_count: number;
    maximum_count: number;
    options: {
        group_option_id: number;
        offering_option_id: number;
        is_default: boolean;
        price_adjustment_minor: string;
        food_item: PublicFood;
        food_category: { name: string };
        serving_option: { label: string; unit: string; quantity: string };
    }[];
};

export type PublicListing = {
    id: number;
    name: string;
    description: string | null;
    food: PublicFood;
    category: { name: string };
    selling_unit: string;
    price_minor: string;
    selling_options: { id: number; label: string; price_minor: string }[];
    minimum_quantity: number;
    quantity_step: number;
    maximum_quantity: number | null;
};

export type PublicService = {
    id: number;
    name: string;
    description: string | null;
    behavior: string;
    packages_count: number;
    listings_count: number;
};

export type PricePreview = {
    quantity: number;
    total_minor: string;
    unit_price_minor?: string;
    price_basis?: string;
    effective_quantity_rules?: {
        minimum_quantity: number;
        quantity_step: number;
        maximum_quantity: number | null;
    };
};
