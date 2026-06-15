import { PublicFooter, PublicNav } from "@/components/public-nav";

const galleryPlaceholders = [
  ["BC", "Buffet Catering"],
  ["FT", "Food Trays"],
  ["FP", "Food Packs"],
  ["LB", "Lechon Belly"],
  ["PP", "Pica-Pica"],
  ["ES", "Event Setup"],
];

export default function GalleryPage() {
  return (
    <main className="bg-neutral-50">
      <PublicNav />
      <section className="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        <div className="max-w-2xl">
          <p className="text-sm font-medium uppercase tracking-[0.2em] text-neutral-500">Gallery</p>
          <h1 className="mt-4 text-4xl font-semibold tracking-tight text-neutral-950">Gallery placeholders.</h1>
          <p className="mt-4 text-sm leading-6 text-neutral-600">
            Real event photos can be uploaded later. For now these placeholders mark the gallery sections the owner will likely want.
          </p>
        </div>
        <div className="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {galleryPlaceholders.map(([initials, label]) => (
            <figure key={label} className="grid aspect-[4/3] place-items-center rounded-md border border-[color:var(--brand-line)] bg-white">
              <div className="text-center">
                <div className="mx-auto grid size-20 place-items-center rounded-md bg-[color:var(--brand-cream)] text-2xl font-semibold text-[color:var(--brand-maroon)]">
                  {initials}
                </div>
                <figcaption className="mt-4 text-sm font-medium text-neutral-700">{label}</figcaption>
              </div>
            </figure>
          ))}
        </div>
      </section>
      <PublicFooter />
    </main>
  );
}
