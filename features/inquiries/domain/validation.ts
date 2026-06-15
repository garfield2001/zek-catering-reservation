import { isValidPax, type PackageRule } from "@/lib/catering";

export function validateInquirySelection({
  packageRule,
  pax,
  dishCount,
  drinkCount,
}: {
  packageRule: PackageRule | undefined;
  pax: number;
  dishCount: number;
  drinkCount: number;
}) {
  if (!packageRule) return "Select a package first.";

  if (!isValidPax(pax, packageRule)) {
    return `Pax must start at ${packageRule.minimum_pax} and increase by ${packageRule.pax_increment}.`;
  }

  if (dishCount > packageRule.meal_slots || drinkCount > packageRule.drink_slots) {
    return "Menu selections exceed the package slots.";
  }

  return null;
}
