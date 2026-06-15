import Image from "next/image";
import Link from "next/link";
import { notFound } from "next/navigation";
import { PublicFooter, PublicNav } from "@/components/public-nav";
import { money } from "@/lib/catering";
import { createClient } from "@/lib/server";

const fallbackImage = "https://images.unsplash.com/photo-1543353071-10c8ba85a904?auto=format&fit=crop&w=1800&q=80";

export default async function PackageDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const supabase = await createClient();
  const [{ data: item }, { data: dishes }, { data: drinks }, { data: addons }] = await Promise.all([
    supabase
      .from("catering_packages")
      .select("id, name, description, price_per_pax, minimum_pax, pax_increment, meal_slots, drink_slots, rice_included, image_url, package_inclusions(label)")
      .eq("id", id)
      .eq("is_active", true)
      .single(),
    supabase
      .from("dishes")
      .select("name, dish_type, premium_price, dish_categories(name)")
      .eq("is_active", true)
      .eq("status", "available")
      .order("name")
      .limit(8),
    supabase.from("drinks").select("name, is_premium, premium_price").eq("is_active", true).eq("status", "available").order("name"),
    supabase.from("addons").select("name, price, category").eq("is_active", true).order("name").limit(6),
  ]);

  if (!item) notFound();

  return (
    <main className="bg-neutral-50">
      <PublicNav />
      <section className="bg-white">
        <div className="mx-auto grid max-w-7xl gap-8 px-4 py-12 sm:px-6 lg:grid-cols-[1.1fr_0.9fr] lg:px-8">
          <div>
            <p className="text-sm font-medium uppercase tracking-[0.2em] text-neutral-500">Package</p>
            <h1 className="mt-4 text-4xl font-semibold tracking-tight text-neutral-950">{item.name}</h1>
            <p className="mt-5 text-sm leading-6 text-neutral-600">{item.description}</p>
            <div className="mt-8 grid gap-3 sm:grid-cols-4">
              <Metric label="Per pax" value={money(item.price_per_pax)} />
              <Metric label="Minimum" value={`${item.minimum_pax}`} />
              <Metric label="Meals" value={`${item.meal_slots}`} />
              <Metric label="Drinks" value={`${item.drink_slots}`} />
            </div>
            <Link href="/inquire" className="mt-8 inline-flex h-11 items-center rounded-md bg-neutral-950 px-5 text-sm font-medium text-white hover:bg-neutral-800">
              Start inquiry
            </Link>
          </div>
          <Image
            src={item.image_url || fallbackImage}
            alt={`${item.name} catering table`}
            width={1400}
            height={1000}
            className="h-full min-h-80 rounded-md object-cover"
          />
        </div>
      </section>

      <section className="mx-auto grid max-w-7xl gap-6 px-4 py-12 sm:px-6 lg:grid-cols-3 lg:px-8">
        <Panel title="Inclusions">
          {(item.package_inclusions ?? []).map((entry) => (
            <Row key={entry.label} primary={entry.label} />
          ))}
          <Row primary={item.rice_included ? "Rice included" : "Rice not included"} />
        </Panel>
        <Panel title="Available meals">
          {(dishes ?? []).map((dish) => {
            const category = Array.isArray(dish.dish_categories) ? dish.dish_categories[0] : dish.dish_categories;
            return (
              <Row
                key={dish.name}
                primary={dish.name}
                secondary={`${category?.name ?? "Menu"}${dish.dish_type === "premium" ? ` - premium ${money(dish.premium_price)}` : ""}`}
              />
            );
          })}
        </Panel>
        <Panel title="Drinks and add-ons">
          {(drinks ?? []).map((drink) => (
            <Row key={drink.name} primary={drink.name} secondary={drink.is_premium ? `Premium ${money(drink.premium_price)}` : "Included option"} />
          ))}
          {(addons ?? []).map((addon) => (
            <Row key={addon.name} primary={addon.name} secondary={`${addon.category ?? "Add-on"} - ${money(addon.price)}`} />
          ))}
        </Panel>
      </section>
      <PublicFooter />
    </main>
  );
}

function Metric({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-md border border-neutral-200 bg-neutral-50 p-4">
      <p className="text-xs uppercase tracking-[0.12em] text-neutral-500">{label}</p>
      <p className="mt-2 text-xl font-semibold text-neutral-950">{value}</p>
    </div>
  );
}

function Panel({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div className="rounded-md border border-neutral-200 bg-white p-5">
      <h2 className="text-lg font-semibold text-neutral-950">{title}</h2>
      <div className="mt-4 space-y-3">{children}</div>
    </div>
  );
}

function Row({ primary, secondary }: { primary: string; secondary?: string }) {
  return (
    <div className="border-b border-neutral-100 pb-3 last:border-0 last:pb-0">
      <p className="text-sm font-medium text-neutral-900">{primary}</p>
      {secondary && <p className="mt-1 text-xs text-neutral-500">{secondary}</p>}
    </div>
  );
}
