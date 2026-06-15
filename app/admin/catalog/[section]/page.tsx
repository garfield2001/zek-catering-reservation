import type { ReactNode } from "react";
import { notFound } from "next/navigation";
import { money } from "@/lib/catering";
import { createClient } from "@/lib/server";

const sections = {
  packages: {
    title: "Packages",
    table: "catering_packages",
    columns: "id, name, price_per_pax, minimum_pax, pax_increment, meal_slots, drink_slots, is_active",
    labels: ["Name", "Price", "Min", "Increment", "Meals", "Drinks", "Active"],
  },
  dishes: {
    title: "Dishes",
    table: "dishes",
    columns: "id, name, dish_type, premium_pricing_mode, premium_price, status, is_active, dish_categories(name)",
    labels: ["Name", "Type", "Premium", "Status", "Active"],
  },
  drinks: {
    title: "Drinks",
    table: "drinks",
    columns: "id, name, is_premium, premium_pricing_mode, premium_price, status, is_active",
    labels: ["Name", "Premium", "Price", "Status", "Active"],
  },
  addons: {
    title: "Add-ons",
    table: "addons",
    columns: "id, name, category, pricing_mode, price, is_active",
    labels: ["Name", "Category", "Mode", "Price", "Active"],
  },
  "food-trays": {
    title: "Food trays",
    table: "food_trays",
    columns: "id, name, price_per_tray, serving_capacity, min_order_quantity, is_active",
    labels: ["Name", "Price", "Capacity", "Min qty", "Active"],
  },
  "food-packs": {
    title: "Food packs",
    table: "food_packs",
    columns: "id, name, price_per_pack, min_quantity, is_active",
    labels: ["Name", "Price", "Min qty", "Active"],
  },
  lechon: {
    title: "Lechon options",
    table: "lechon_options",
    columns: "id, name, weight_kg, price, is_active",
    labels: ["Name", "Weight", "Price", "Active"],
  },
} as const;

export default async function AdminCatalogSectionPage({ params }: { params: Promise<{ section: string }> }) {
  const { section } = await params;
  const config = sections[section as keyof typeof sections];
  if (!config) notFound();

  const supabase = await createClient();
  const { data } = await (supabase as never as { from: (table: string) => { select: (columns: string) => { limit: (count: number) => Promise<{ data: Record<string, unknown>[] | null }> } } })
    .from(config.table)
    .select(config.columns)
    .limit(200);

  return (
    <div className="space-y-6">
      <section className="rounded-md border border-neutral-200 bg-white p-6">
        <p className="text-xs font-medium uppercase tracking-[0.18em] text-neutral-500">Catalog</p>
        <h1 className="mt-3 text-3xl font-semibold tracking-tight text-neutral-950">{config.title}</h1>
        <p className="mt-2 text-sm text-neutral-600">
          Catalog records from Supabase. Active and available items are visible to customers; inactive or unavailable records remain preserved for old snapshots.
        </p>
      </section>

      <section className="grid gap-3 sm:grid-cols-7">
        {Object.keys(sections).map((key) => (
          <a
            key={key}
            href={`/admin/catalog/${key}`}
            className={`rounded-md border px-3 py-2 text-center text-sm font-medium ${key === section ? "border-neutral-950 bg-neutral-950 text-white" : "border-neutral-200 bg-white text-neutral-700 hover:bg-neutral-50"}`}
          >
            {sections[key as keyof typeof sections].title}
          </a>
        ))}
      </section>

      <section className="overflow-hidden rounded-md border border-neutral-200 bg-white">
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-neutral-200 text-sm">
            <thead className="bg-neutral-50 text-left text-xs uppercase tracking-[0.12em] text-neutral-500">
              <tr>
                {config.labels.map((label) => (
                  <th key={label} className="px-4 py-3 font-medium">{label}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-200">
              {(data ?? []).map((row: Record<string, unknown>) => (
                <tr key={String(row.id)} className="hover:bg-neutral-50">
                  {renderCells(section, row).map((cell, index) => (
                    <td key={`${row.id}-${index}`} className="whitespace-nowrap px-4 py-4 text-neutral-700">
                      {cell}
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>
    </div>
  );
}

function renderCells(section: string, row: Record<string, unknown>): ReactNode[] {
  switch (section) {
    case "packages":
      return [row.name as string, money(row.price_per_pax as number), row.minimum_pax as number, row.pax_increment as number, row.meal_slots as number, row.drink_slots as number, yes(row.is_active)];
    case "dishes":
      return [<DishName key="dish-name" name={String(row.name ?? "Untitled dish")} />, row.dish_type as string, money(row.premium_price as number), row.status as string, yes(row.is_active)];
    case "drinks":
      return [row.name as string, yes(row.is_premium), money(row.premium_price as number), row.status as string, yes(row.is_active)];
    case "addons":
      return [row.name as string, (row.category as string | null) ?? "-", row.pricing_mode as string, money(row.price as number), yes(row.is_active)];
    case "food-trays":
      return [row.name as string, money(row.price_per_tray as number), (row.serving_capacity as string | null) ?? "-", row.min_order_quantity as number, yes(row.is_active)];
    case "food-packs":
      return [row.name as string, money(row.price_per_pack as number), row.min_quantity as number, yes(row.is_active)];
    case "lechon":
      return [row.name as string, row.weight_kg ? `${row.weight_kg} kg` : "-", money(row.price as number), yes(row.is_active)];
    default:
      return [];
  }
}

function DishName({ name }: { name: string }) {
  return (
    <div className="flex items-center gap-3">
      <div className="flex size-10 shrink-0 items-center justify-center rounded-md bg-amber-100 text-xs font-semibold text-amber-900 ring-1 ring-amber-200">
        {initials(name)}
      </div>
      <span className="font-medium text-neutral-900">{name}</span>
    </div>
  );
}

function initials(name: string) {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join("") || "DI";
}

function yes(value: unknown) {
  return value ? "Yes" : "No";
}
