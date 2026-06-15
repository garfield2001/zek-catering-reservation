import { PublicFooter, PublicNav } from "@/components/public-nav";
import { InquiryBuilder } from "@/components/inquiry-builder";

export default function ReservePage() {
  return (
    <main className="bg-neutral-50">
      <PublicNav />
      <section className="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 lg:grid-cols-[0.8fr_1.2fr] lg:px-8">
        <div>
          <p className="text-sm font-medium uppercase tracking-[0.2em] text-neutral-500">Request a quote</p>
          <h1 className="mt-4 text-4xl font-semibold tracking-tight text-neutral-950">
            Tell us about your event.
          </h1>
          <p className="mt-5 text-sm leading-6 text-neutral-600">
            Send your event details, preferred package, menu choices, add-ons, and special requests. Staff will review
            the details and send the final quotation before any payment is needed.
          </p>
          <div className="mt-8 grid gap-3 text-sm text-neutral-700">
            {[
              "Online inquiries require an event date at least 14 days away.",
              "Customer edits are available within 24 hours while the inquiry is still unlocked.",
              "Your inquiry does not reserve the event date yet.",
              "Staff will contact you if details need final confirmation.",
            ].map((item) => (
              <div key={item} className="rounded-md border border-neutral-200 bg-white p-4">
                {item}
              </div>
            ))}
          </div>
        </div>
        <InquiryBuilder />
      </section>
      <PublicFooter />
    </main>
  );
}
