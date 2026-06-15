export type PricingMode = "none" | "flat" | "per_pax" | "per_quantity" | "per_pack" | "manual";

export type PackageRule = {
  id: string;
  name: string;
  price_per_pax: number | string;
  minimum_pax: number;
  pax_increment: number;
  meal_slots: number;
  drink_slots: number;
};

export type PricedItem = {
  id: string;
  name: string;
  price?: number | string;
  premium_price?: number | string;
  premium_pricing_mode?: PricingMode;
  pricing_mode?: PricingMode;
  dish_type?: "standard" | "premium";
  is_premium?: boolean;
};

export type PricingInput = {
  package: PackageRule;
  pax: number;
  dishes?: PricedItem[];
  drinks?: PricedItem[];
  addons?: Array<PricedItem & { quantity?: number }>;
  foodTrays?: Array<PricedItem & { quantity?: number; price_per_tray?: number | string }>;
  foodPacks?: Array<PricedItem & { quantity?: number; price_per_pack?: number | string }>;
  lechon?: Array<PricedItem & { quantity?: number }>;
  transportationFee?: number;
  emergencyFee?: number;
  manualCharges?: number;
  discount?: number;
  reservationFeeType?: "fixed" | "percentage";
  reservationFeeValue?: number;
};

export function money(value: number | string | null | undefined) {
  return `PHP ${Number(value ?? 0).toLocaleString("en-PH", {
    maximumFractionDigits: 0,
  })}`;
}

export function isValidPax(pax: number, packageRule: Pick<PackageRule, "minimum_pax" | "pax_increment">) {
  if (!Number.isFinite(pax)) return false;
  if (pax < packageRule.minimum_pax) return false;
  return (pax - packageRule.minimum_pax) % packageRule.pax_increment === 0;
}

export function addByMode(mode: PricingMode | undefined, price: number, pax: number, quantity = 1) {
  switch (mode) {
    case "per_pax":
      return price * pax;
    case "per_quantity":
    case "per_pack":
    case "flat":
      return price * quantity;
    case "manual":
      return price;
    default:
      return 0;
  }
}

export function calculateQuotation(input: PricingInput) {
  const pax = input.pax;
  const base = Number(input.package.price_per_pax) * pax;
  const premiumDishes = (input.dishes ?? []).reduce((sum, item) => {
    if (item.dish_type !== "premium") return sum;
    return sum + addByMode(item.premium_pricing_mode, Number(item.premium_price ?? 0), pax);
  }, 0);
  const premiumDrinks = (input.drinks ?? []).reduce((sum, item) => {
    if (!item.is_premium) return sum;
    return sum + addByMode(item.premium_pricing_mode, Number(item.premium_price ?? 0), pax);
  }, 0);
  const addons = (input.addons ?? []).reduce(
    (sum, item) => sum + addByMode(item.pricing_mode, Number(item.price ?? 0), pax, item.quantity ?? 1),
    0,
  );
  const foodTrays = (input.foodTrays ?? []).reduce(
    (sum, item) => sum + Number(item.price_per_tray ?? item.price ?? 0) * (item.quantity ?? 1),
    0,
  );
  const foodPacks = (input.foodPacks ?? []).reduce(
    (sum, item) => sum + Number(item.price_per_pack ?? item.price ?? 0) * (item.quantity ?? 1),
    0,
  );
  const lechon = (input.lechon ?? []).reduce(
    (sum, item) => sum + Number(item.price ?? 0) * (item.quantity ?? 1),
    0,
  );

  const subtotal =
    base +
    premiumDishes +
    premiumDrinks +
    addons +
    foodTrays +
    foodPacks +
    lechon +
    Number(input.transportationFee ?? 0) +
    Number(input.emergencyFee ?? 0) +
    Number(input.manualCharges ?? 0);
  const total = Math.max(0, subtotal - Number(input.discount ?? 0));
  const reservationFee =
    input.reservationFeeType === "percentage"
      ? (total * Number(input.reservationFeeValue ?? 0)) / 100
      : Number(input.reservationFeeValue ?? 0);

  return {
    base,
    premiumDishes,
    premiumDrinks,
    addons,
    foodTrays,
    foodPacks,
    lechon,
    subtotal,
    total,
    reservationFee,
  };
}

export function uniqueValues(values: string[]) {
  return Array.from(new Set(values.filter(Boolean)));
}

export function addDays(date: Date, days: number) {
  const next = new Date(date);
  next.setDate(next.getDate() + days);
  return next;
}

export function dateOnly(date: Date) {
  return date.toISOString().slice(0, 10);
}
