import type { Addon, Dish, Drink, InquiryTotals } from "./types";
import type { PackageRule } from "@/lib/catering";

export function calculateInquiryTotals({
  packageRule,
  pax,
  selectedDishes,
  selectedDrinks,
  selectedAddons,
}: {
  packageRule: PackageRule | undefined;
  pax: number;
  selectedDishes: Dish[];
  selectedDrinks: Drink[];
  selectedAddons: Addon[];
}): InquiryTotals {
  if (!packageRule) return { base: 0, premiums: 0, addons: 0, total: 0 };

  const base = Number(packageRule.price_per_pax) * pax;
  const premiums = [...selectedDishes, ...selectedDrinks].reduce((sum, item) => {
    const premium = "dish_type" in item ? item.dish_type === "premium" : item.is_premium;
    if (!premium) return sum;

    const price = Number(item.premium_price ?? 0);
    return sum + (item.premium_pricing_mode === "per_pax" ? price * pax : price);
  }, 0);
  const addons = selectedAddons.reduce((sum, item) => sum + Number(item.price ?? 0), 0);

  return { base, premiums, addons, total: base + premiums + addons };
}
