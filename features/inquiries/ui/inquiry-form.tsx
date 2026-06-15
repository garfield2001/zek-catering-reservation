"use client";

import type { FormEvent } from "react";
import { AlertCircle, Loader2, Send } from "lucide-react";
import { money, type PackageRule } from "@/lib/catering";
import { requirementOptions } from "../domain/constants";
import type { Addon, Catalog, InquirySelection, InquiryTotals } from "../domain/types";
import { ChoiceCard, Field, PickerHeader } from "./form-controls";
import { SummaryPanel } from "./summary-panel";

type InquiryFormProps = {
  catalog: Catalog;
  selectedPackage: PackageRule | undefined;
  selectedPackageId: string;
  pax: number;
  minEventDate: string;
  selection: InquirySelection;
  selectedAddons: Addon[];
  totals: InquiryTotals;
  loading: boolean;
  error: string | null;
  onPackageChange: (packageId: string) => void;
  onPaxChange: (pax: number) => void;
  onToggleDish: (dishId: string) => void;
  onToggleDrink: (drinkId: string) => void;
  onToggleAddon: (addonId: string) => void;
  onToggleRequirement: (requirement: string) => void;
  onSubmit: (event: FormEvent<HTMLFormElement>) => void;
};

export function InquiryForm(props: InquiryFormProps) {
  return (
    <form onSubmit={props.onSubmit} className="mt-8 grid gap-6">
      <section className="rounded-md border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-950">
        <p className="font-semibold">Inquiry first, reservation after review.</p>
        <p className="mt-1 text-amber-900">Your date is secured after staff approval and reservation-fee verification.</p>
      </section>

      <CustomerFields minEventDate={props.minEventDate} />
      <PackageSection {...props} />
      <MealSection {...props} />
      <DrinkSection {...props} />
      <AddonSection {...props} />
      <RequirementSection {...props} />

      <section className="rounded-md border border-neutral-200 bg-white p-5 shadow-sm">
        <label className="text-sm font-medium text-neutral-800">
          Notes
          <textarea name="notes" rows={4} placeholder="Tell us your preferred motif, venue details, food restrictions, or special requests." className="mt-2 w-full rounded-md border border-neutral-300 px-3 py-3 text-sm outline-none focus:border-neutral-950" />
        </label>
      </section>

      <SummaryPanel totals={props.totals} />
      <SubmitFeedback error={props.error} loading={props.loading} />
    </form>
  );
}

function CustomerFields({ minEventDate }: { minEventDate: string }) {
  return (
    <section className="grid gap-4 rounded-md border border-neutral-200 bg-white p-5 shadow-sm md:grid-cols-2">
      <Field label="Full name" name="customer_name" required placeholder="Juan Dela Cruz" />
      <Field label="Contact number" name="customer_phone" required placeholder="09xx xxx xxxx" />
      <Field label="Email (optional)" name="customer_email" type="email" placeholder="name@email.com" />
      <Field label="Event type" name="event_type" required placeholder="Birthday, wedding, corporate event" />
      <Field label="Event date" name="event_date" type="date" required min={minEventDate} />
      <Field label="Event time" name="event_time" type="time" required />
      <Field label="Venue name (optional)" name="venue_name" placeholder="Home, resort, office, church, school" />
      <Field label="Street / block / lot" name="venue_address" required placeholder="House number, street, subdivision, landmark" />
      <Field label="Barangay" name="barangay" placeholder="Barangay" />
      <Field label="City" name="city" required placeholder="General Santos City" />
      <Field label="Province (optional)" name="province" placeholder="South Cotabato" />
    </section>
  );
}

function PackageSection({ catalog, selectedPackage, selectedPackageId, pax, onPackageChange, onPaxChange }: InquiryFormProps) {
  return (
    <section className="grid gap-4 rounded-md border border-neutral-200 bg-white p-5 shadow-sm md:grid-cols-[1.4fr_0.6fr]">
      <label className="text-sm font-medium text-neutral-800">
        Package
        <select value={selectedPackageId} onChange={(event) => onPackageChange(event.target.value)} className="mt-2 h-11 w-full rounded-md border border-neutral-300 px-3 text-sm outline-none focus:border-neutral-950">
          {catalog.packages.map((packageRule) => (
            <option key={packageRule.id} value={packageRule.id}>
              {packageRule.name} - {money(packageRule.price_per_pax)}/pax
            </option>
          ))}
        </select>
      </label>
      <label className="text-sm font-medium text-neutral-800">
        Number of guests
        <input type="number" value={pax} min={selectedPackage?.minimum_pax ?? 1} step={selectedPackage?.pax_increment ?? 1} onChange={(event) => onPaxChange(Number(event.target.value))} className="mt-2 h-11 w-full rounded-md border border-neutral-300 px-3 text-sm outline-none focus:border-neutral-950" />
        <span className="mt-2 block text-xs font-normal text-neutral-500">Minimum 50 pax for catering packages.</span>
      </label>
      <p className="text-sm leading-6 text-neutral-600 md:col-span-2">
        All packages share the same buffet inclusions and available dish list. Choose the package based on how many
        meal choices you want included.
      </p>
    </section>
  );
}

function MealSection({ catalog, selectedPackage, selection, onToggleDish }: InquiryFormProps) {
  const groups = ["Beef", "Pork", "Chicken"].map((category) => ({
    category,
    dishes: catalog.dishes.filter((dish) => dish.dish_categories?.name === category),
  }));
  const premiumDishes = catalog.dishes.filter((dish) => dish.dish_type === "premium" && !groups.some((group) => group.dishes.some((item) => item.id === dish.id)));

  return (
    <section className="grid gap-4 rounded-md border border-neutral-200 bg-white p-5 shadow-sm">
      <PickerHeader title="Meal choices" detail={`${selection.dishIds.length}/${selectedPackage?.meal_slots ?? 0} selected`} />
      <p className="text-sm text-neutral-500">Pick preferred dishes now or leave slots open for staff consultation.</p>
      {groups.map((group) => (
        <div key={group.category} className="grid gap-3">
          <h3 className="text-sm font-semibold uppercase tracking-[0.14em] text-[color:var(--brand-maroon)]">{group.category}</h3>
          <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            {group.dishes.length ? group.dishes.map((dish) => {
              const premiumText = dish.dish_type === "premium" ? `Premium ${money(dish.premium_price)}` : "Included choice";
              return (
                <ChoiceCard
                  key={dish.id}
                  selected={selection.dishIds.includes(dish.id)}
                  title={dish.name}
                  meta={premiumText}
                  description={dish.description}
                  onClick={() => onToggleDish(dish.id)}
                />
              );
            }) : <p className="text-sm text-neutral-500">No available {group.category.toLowerCase()} dishes yet.</p>}
          </div>
        </div>
      ))}
      {premiumDishes.length > 0 && (
        <div className="grid gap-3 border-t border-neutral-200 pt-4">
          <h3 className="text-sm font-semibold uppercase tracking-[0.14em] text-[color:var(--brand-red)]">Premium add-on dishes</h3>
          <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            {premiumDishes.map((dish) => (
              <ChoiceCard key={dish.id} selected={selection.dishIds.includes(dish.id)} title={dish.name} meta={`Premium ${money(dish.premium_price)}`} description={dish.description} onClick={() => onToggleDish(dish.id)} />
            ))}
          </div>
        </div>
      )}
    </section>
  );
}

function DrinkSection({ catalog, selectedPackage, selection, onToggleDrink }: InquiryFormProps) {
  return (
    <section className="grid gap-4 rounded-md border border-neutral-200 bg-white p-5 shadow-sm">
      <PickerHeader title="Drinks" detail={`${selection.drinkIds.length}/${selectedPackage?.drink_slots ?? 0} selected`} />
      <p className="text-sm text-neutral-500">You may also finalize drinks during consultation.</p>
      <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        {catalog.drinks.map((drink) => {
          const premiumText = drink.is_premium ? `Premium ${money(drink.premium_price)}` : "Included";
          return <ChoiceCard key={drink.id} selected={selection.drinkIds.includes(drink.id)} title={drink.name} meta={premiumText} description={drink.description} onClick={() => onToggleDrink(drink.id)} />;
        })}
      </div>
    </section>
  );
}

function AddonSection({ catalog, selection, onToggleAddon }: InquiryFormProps) {
  return (
    <section className="grid gap-4 rounded-md border border-neutral-200 bg-white p-5 shadow-sm">
      <PickerHeader title="Optional add-ons" detail={`${selection.addonIds.length} selected`} />
      <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        {catalog.addons.map((addon) => (
          <ChoiceCard key={addon.id} selected={selection.addonIds.includes(addon.id)} title={addon.name} meta={`${addon.category ?? "Add-on"} - ${money(addon.price)}`} description={addon.description} onClick={() => onToggleAddon(addon.id)} />
        ))}
      </div>
    </section>
  );
}

function RequirementSection({ selection, onToggleRequirement }: InquiryFormProps) {
  return (
    <section className="grid gap-4 rounded-md border border-neutral-200 bg-white p-5 shadow-sm">
      <PickerHeader title="Special requirements" detail={`${selection.requirements.length} selected`} />
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        {requirementOptions.map((item) => (
          <label key={item} className="flex items-center gap-3 rounded-md border border-neutral-200 bg-neutral-50 px-4 py-3 text-sm font-medium text-neutral-800">
            <input type="checkbox" checked={selection.requirements.includes(item)} onChange={() => onToggleRequirement(item)} className="size-4 accent-neutral-950" />
            {item}
          </label>
        ))}
      </div>
    </section>
  );
}

function SubmitFeedback({ error, loading }: { error: string | null; loading: boolean }) {
  return (
    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      {error ? (
        <div className="flex items-start gap-2 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
          <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
          <span>{error}</span>
        </div>
      ) : (
        <span className="text-sm text-neutral-500">Staff will confirm availability before sending payment instructions.</span>
      )}
      <button type="submit" disabled={loading} className="inline-flex h-12 items-center justify-center gap-2 rounded-md bg-neutral-950 px-6 text-sm font-semibold text-white hover:bg-neutral-800 disabled:cursor-not-allowed disabled:bg-neutral-400">
        {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
        {loading ? "Submitting inquiry" : "Submit inquiry"}
      </button>
    </div>
  );
}
