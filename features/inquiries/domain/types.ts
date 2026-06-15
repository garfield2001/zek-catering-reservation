import type { PackageRule } from "@/lib/catering";

export type Dish = {
  id: string;
  name: string;
  description: string | null;
  dish_type: "standard" | "premium";
  premium_pricing_mode: "none" | "flat" | "per_pax";
  premium_price: number;
  dish_categories?: { name: string } | null;
};

export type Drink = {
  id: string;
  name: string;
  description: string | null;
  is_premium: boolean;
  premium_pricing_mode: "none" | "flat" | "per_pax";
  premium_price: number;
};

export type Addon = {
  id: string;
  name: string;
  description: string | null;
  category: string | null;
  pricing_mode: "flat" | "per_pax" | "per_quantity" | "manual";
  price: number;
};

export type Catalog = {
  packages: PackageRule[];
  dishes: Dish[];
  drinks: Drink[];
  addons: Addon[];
};

export type InquiryTotals = {
  base: number;
  premiums: number;
  addons: number;
  total: number;
};

export type InquirySelection = {
  dishIds: string[];
  drinkIds: string[];
  addonIds: string[];
  requirements: string[];
};

export type InquiryResult = {
  refCode: string;
};
