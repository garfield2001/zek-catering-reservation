import { createClient } from "@/lib/client";
import { catalogLoadError } from "../domain/constants";
import type { Addon, Catalog, Dish, Drink } from "../domain/types";
import type { PackageRule } from "@/lib/catering";

export async function loadInquiryCatalog(signal: AbortSignal): Promise<Catalog> {
  const supabase = createClient();
  const [packages, dishes, drinks, addons] = await Promise.all([
    supabase
      .from("catering_packages")
      .select("id, name, price_per_pax, minimum_pax, pax_increment, meal_slots, drink_slots")
      .eq("is_active", true)
      .order("sort_order")
      .abortSignal(signal),
    supabase
      .from("dishes")
      .select("id, name, description, dish_type, premium_pricing_mode, premium_price, dish_categories(name)")
      .eq("is_active", true)
      .eq("status", "available")
      .order("name")
      .abortSignal(signal),
    supabase
      .from("drinks")
      .select("id, name, description, is_premium, premium_pricing_mode, premium_price")
      .eq("is_active", true)
      .eq("status", "available")
      .order("name")
      .abortSignal(signal),
    supabase
      .from("addons")
      .select("id, name, description, category, pricing_mode, price")
      .eq("is_active", true)
      .order("name")
      .abortSignal(signal),
  ]);

  if (packages.error || dishes.error || drinks.error || addons.error) {
    throw new Error(catalogLoadError);
  }

  return {
    packages: (packages.data ?? []) as PackageRule[],
    dishes: (dishes.data ?? []) as unknown as Dish[],
    drinks: (drinks.data ?? []) as Drink[],
    addons: (addons.data ?? []) as Addon[],
  };
}
