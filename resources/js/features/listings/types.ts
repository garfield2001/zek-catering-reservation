import type { Collection, FoodItem } from "../catalog/types";
export type Listing = {
    id: number;
    family_id: number;
    dish_id: number;
    offering_option_id: number | null;
    active: boolean;
    archived_at: string | null;
    current_version_id: number | null;
};
export type ListingVersion = {
    id: number;
    version_number: number;
    draft_revision: number;
    name: string;
    description: string | null;
    status: "draft" | "published" | "abandoned";
    price_minor: string;
    selling_unit: string;
    minimum_quantity: number;
    quantity_step: number;
    maximum_quantity: number | null;
    published_at: string | null;
    uses_food_name?: boolean;
    has_selling_options?: boolean;
    selling_options?: ListingSellingOption[];
};
export type ListingSellingOption = {
    id: number;
    label: string;
    price_minor: string;
    sort_order: number;
};
export type ListingFood = Omit<FoodItem, "offerings"> & {
    offerings: {
        id: number;
        options: {
            id: number;
            label: string;
            quantity: string;
            unit: string;
        }[];
    }[];
};
export type ListingEditorProps = {
    family: Collection;
    listing: Listing | null;
    version: ListingVersion | null;
    foods: ListingFood[];
    canPrice: boolean;
};
export type ListingSnapshot = Pick<
    ListingVersion,
    | "name"
    | "description"
    | "price_minor"
    | "selling_unit"
    | "minimum_quantity"
    | "quantity_step"
    | "maximum_quantity"
> & {
    selling_options?: ListingSellingOption[];
    service: { name: string };
    food: { name: string };
    category: { name: string };
    serving_option: {
        label: string;
        quantity: string;
        unit: string;
        serving_pax: number | null;
        serving_claim: string | null;
    } | null;
};
export type ListingDetailsProps = {
    commercialHistory:
        | import("../catalog/components/commercial-history").CommercialHistoryData
        | null;
    listing: Listing;
    snapshot: ListingSnapshot;
    version: Pick<
        ListingVersion,
        "id" | "version_number" | "status" | "draft_revision" | "published_at"
    >;
    image_url: string | null;
    thumbnail_url: string | null;
    availability: { available: boolean; reasons: string[] };
    draft_id: number | null;
    history: Pick<
        ListingVersion,
        "id" | "version_number" | "status" | "published_at"
    >[];
    canManage: boolean;
    canPublish: boolean;
    canHistory: boolean;
    canLifecycle: boolean;
};
