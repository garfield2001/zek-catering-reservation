import { PublicFooter, PublicNav } from "@/components/public-nav";
import { services } from "@/lib/site-data";

const process = [
  ["Inquiry", "Customer sends event date, venue, pax, package, preferred meals, and add-ons."],
  ["Quotation", "Staff reviews availability, transportation, premium dishes, and payment instructions."],
  ["Reservation fee", "Customer uploads proof after quotation. Staff verifies payment before the date is reserved."],
  ["Event service", "Team prepares setup, food, service timing, final payment, and event completion."],
];

export default function ServicesPage() {
  return (
    <main className="bg-neutral-50">
      <PublicNav />
      <section className="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        <div className="grid gap-10 lg:grid-cols-[0.9fr_1.1fr]">
          <div>
            <p className="text-sm font-medium uppercase tracking-[0.2em] text-neutral-500">Services</p>
            <h1 className="mt-4 text-4xl font-semibold tracking-tight text-neutral-950">
              Catering operations from first inquiry to final plate.
            </h1>
          </div>
          <div className="grid gap-3 sm:grid-cols-2">
            {services.map((service) => (
              <div key={service} className="rounded-md border border-[color:var(--brand-line)] bg-white p-4 text-sm text-neutral-800 shadow-sm">
                {service}
              </div>
            ))}
          </div>
        </div>
        <div className="mt-14 grid gap-4 md:grid-cols-4">
          {process.map(([title, copy]) => (
            <div key={title} className="rounded-md border border-[color:var(--brand-line)] bg-white p-5">
              <h2 className="text-lg font-semibold text-neutral-950">{title}</h2>
              <p className="mt-3 text-sm leading-6 text-neutral-600">{copy}</p>
            </div>
          ))}
        </div>
      </section>
      <PublicFooter />
    </main>
  );
}
