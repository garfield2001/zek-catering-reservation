import { PublicFooter, PublicNav } from "@/components/public-nav";
import { TrackingPanel } from "@/components/tracking-panel";

export default function TrackPage() {
  return (
    <main className="bg-neutral-50">
      <PublicNav />
      <section className="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        <div className="max-w-3xl">
          <p className="text-sm font-medium uppercase tracking-[0.2em] text-neutral-500">Track</p>
          <h1 className="mt-4 text-4xl font-semibold tracking-tight text-neutral-950">Check inquiry, quotation, and payment status.</h1>
          <p className="mt-4 text-sm leading-6 text-neutral-600">
            Use your reference code and contact number. Customer tracking never exposes internal staff notes.
          </p>
        </div>
        <div className="mt-8">
          <TrackingPanel />
        </div>
      </section>
      <PublicFooter />
    </main>
  );
}
