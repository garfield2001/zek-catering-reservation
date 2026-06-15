import Link from "next/link";
import { PublicFooter, PublicNav } from "@/components/public-nav";
import { money } from "@/lib/catering";
import { createClient } from "@/lib/server";

export default async function PackagesPage() {
  const supabase = await createClient();
  const { data: packages } = await supabase
    .from("catering_packages")
    .select("id, name, description, price_per_pax, minimum_pax, meal_slots, drink_slots, rice_included, package_inclusions(label)")
    .eq("is_active", true)
    .order("sort_order");

  return (
    <main className="bg-neutral-50">
      <PublicNav />
      <section className="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        <div className="max-w-3xl">
          <p className="text-sm font-medium uppercase tracking-[0.2em] text-neutral-500">Packages</p>
          <h1 className="mt-4 text-4xl font-semibold tracking-tight text-neutral-950">Catering packages with one shared menu list.</h1>
          <p className="mt-4 text-sm leading-6 text-neutral-600">
            Every package has the same catering inclusions, rice, drink slot, and menu choices. The difference is the
            price per pax and how many meal choices are included.
          </p>
        </div>
        <div className="mt-10 grid gap-6 md:grid-cols-2 xl:grid-cols-4">
          {(packages ?? []).map((item) => (
            <article key={item.id} className="rounded-md border border-[color:var(--brand-line)] bg-white shadow-sm">
              <div className="p-6">
                <div className="flex items-start justify-between gap-3">
                  <h2 className="text-2xl font-semibold text-neutral-950">{item.name}</h2>
                  <span className="rounded-md bg-[color:var(--brand-cream)] px-2 py-1 text-sm font-semibold text-[color:var(--brand-maroon)]">
                    {money(item.price_per_pax)}/pax
                  </span>
                </div>
                <p className="mt-3 text-sm text-neutral-500">Minimum {item.minimum_pax} pax</p>
                <div className="mt-5 grid grid-cols-2 gap-2 text-center text-xs text-neutral-600">
                  <Rule label="Meals" value={item.meal_slots} />
                  <Rule label="Drinks" value={item.drink_slots} />
                </div>
                <ul className="mt-5 space-y-2 text-sm text-neutral-700">
                  <li className="flex items-center gap-2">
                    <span className="h-1.5 w-1.5 rounded-full bg-[color:var(--brand-orange)]" />
                    Rice included
                  </li>
                  {(item.package_inclusions ?? []).slice(0, 3).map((inclusion) => (
                    <li key={`${item.id}-${inclusion.label}`} className="flex items-center gap-2">
                      <span className="h-1.5 w-1.5 rounded-full bg-[color:var(--brand-orange)]" />
                      {inclusion.label}
                    </li>
                  ))}
                </ul>
                <div className="mt-6">
                  <Link href="/inquire" className="inline-flex h-10 w-full items-center justify-center rounded-md bg-[color:var(--brand-maroon)] px-4 text-sm font-medium text-white hover:bg-[color:var(--brand-brown)]">
                    Inquire
                  </Link>
                </div>
              </div>
            </article>
          ))}
        </div>
      </section>
      <PublicFooter />
    </main>
  );
}

function Rule({ label, value }: { label: string; value: number }) {
  return (
    <div className="rounded-md border border-[color:var(--brand-line)] bg-[color:var(--brand-cream)] p-2">
      <p className="text-lg font-semibold text-neutral-950">{value}</p>
      <p>{label}</p>
    </div>
  );
}
