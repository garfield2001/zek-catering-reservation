import Link from "next/link";
import Image from "next/image";
import { PublicFooter, PublicNav } from "@/components/public-nav";
import { services } from "@/lib/site-data";

const eventPhotos = [
  {
    src: "/images/landing/catering-buffet-table.jpg",
    alt: "Buffet table with trays of catered food",
    title: "Buffet catering",
  },
  {
    src: "/images/landing/filipino-lechon-plates.jpg",
    alt: "Filipino lechon plates with yellow rice",
    title: "Lechon and party food",
  },
  {
    src: "/images/landing/food-tray-service.jpg",
    alt: "Food being served from foil trays at an event",
    title: "Food tray service",
  },
];

export default function Home() {
  return (
    <main className="bg-neutral-50">
      <PublicNav />
      <section
        className="relative flex min-h-[72vh] items-end bg-neutral-950 bg-cover bg-center"
        style={{
          backgroundImage:
            "linear-gradient(90deg, rgba(140,27,36,.88), rgba(140,56,7,.48)), url('/images/landing/catering-buffet-table.jpg')",
        }}
      >
        <div className="mx-auto w-full max-w-7xl px-4 pb-16 pt-32 text-white sm:px-6 lg:px-8">
          <p className="text-sm font-medium uppercase tracking-[0.22em] text-orange-100">Catering and kitchen services</p>
          <h1 className="mt-5 max-w-3xl text-5xl font-semibold leading-tight sm:text-6xl lg:text-7xl">Zek Catering</h1>
          <p className="mt-6 max-w-2xl text-base leading-7 text-neutral-200 sm:text-lg">
            Buffet catering, food trays, food packs, pica-pica, and lechon add-ons for family celebrations, school
            events, corporate meals, baptisms, birthdays, and weddings.
          </p>
          <div className="mt-8 flex flex-wrap gap-3">
            <Link href="/inquire" className="inline-flex h-11 items-center rounded-md bg-white px-5 text-sm font-medium text-[color:var(--brand-maroon)] hover:bg-orange-50">
              Request a quote
            </Link>
            <Link href="/packages" className="inline-flex h-11 items-center rounded-md border border-white/50 px-5 text-sm font-medium text-white hover:bg-white/10">
              View packages
            </Link>
          </div>
        </div>
      </section>

      <section className="border-b border-neutral-200 bg-white">
        <div className="mx-auto grid max-w-7xl gap-8 px-4 py-12 sm:px-6 md:grid-cols-4 lg:px-8">
          {[
            ["50", "minimum catering pax"],
            ["10", "minimum food packs"],
            ["12-15", "pax per food tray"],
            ["4 hrs", "catering service"],
          ].map(([value, label]) => (
            <div key={label}>
              <p className="text-3xl font-semibold text-neutral-950">{value}</p>
              <p className="mt-2 text-sm text-neutral-500">{label}</p>
            </div>
          ))}
        </div>
      </section>

      <section className="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
        <div className="grid gap-10 lg:grid-cols-[0.8fr_1.2fr]">
          <div>
            <p className="text-sm font-medium uppercase tracking-[0.2em] text-neutral-500">Services</p>
            <h2 className="mt-4 text-3xl font-semibold tracking-tight text-neutral-950">Everything staff needs to review before quoting.</h2>
            <p className="mt-4 text-sm leading-6 text-neutral-600">
              Customers submit the event date, venue, pax, package, menu preferences, add-ons, and special requests.
              Staff then prepares the final quotation and payment instructions.
            </p>
          </div>
          <div className="grid gap-3 sm:grid-cols-2">
            {services.map((service) => (
              <div key={service} className="rounded-md border border-[color:var(--brand-line)] bg-white p-4 text-sm text-neutral-800">
                {service}
              </div>
            ))}
          </div>
        </div>

        <div className="mt-12 grid gap-4 md:grid-cols-3">
          {eventPhotos.map((photo) => (
            <article key={photo.src} className="overflow-hidden rounded-md border border-neutral-200 bg-white">
              <Image src={photo.src} alt={photo.alt} width={1200} height={800} className="h-56 w-full object-cover" />
              <div className="p-4">
                <h3 className="font-semibold text-neutral-950">{photo.title}</h3>
              </div>
            </article>
          ))}
        </div>
      </section>

      <section className="bg-white">
        <div className="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
          <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
              <p className="text-sm font-medium uppercase tracking-[0.2em] text-neutral-500">Packages</p>
              <h2 className="mt-4 text-3xl font-semibold tracking-tight text-neutral-950">Same catering inclusions, different meal counts.</h2>
            </div>
            <Link href="/packages" className="text-sm font-medium text-neutral-950 underline underline-offset-4">
              Compare packages
            </Link>
          </div>
          <div className="mt-8 grid gap-6 md:grid-cols-2 xl:grid-cols-4">
            {["Package A", "Package B", "Package C", "Package D"].map((name, index) => (
              <article key={name} className="rounded-md border border-[color:var(--brand-line)] bg-white">
                <div className="p-5">
                  <div className="flex items-start justify-between gap-4">
                    <h3 className="text-xl font-semibold text-neutral-950">{name}</h3>
                    <p className="text-sm font-medium text-[color:var(--brand-maroon)]">{4 + index} meals</p>
                  </div>
                  <p className="mt-3 text-sm leading-6 text-neutral-600">
                    Minimum 50 pax, rice included, 1 drink choice, buffet setup, and the same available dish list.
                  </p>
                </div>
              </article>
            ))}
          </div>
        </div>
      </section>
      <PublicFooter />
    </main>
  );
}
