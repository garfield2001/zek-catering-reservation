import { PublicFooter, PublicNav } from "@/components/public-nav";
import { TrackingPanel } from "@/components/tracking-panel";

export default async function FeedbackTokenPage({ params }: { params: Promise<{ token: string }> }) {
  const { token } = await params;

  return (
    <main className="bg-neutral-50">
      <PublicNav />
      <section className="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        <div className="max-w-3xl">
          <p className="text-sm font-medium uppercase tracking-[0.2em] text-neutral-500">Feedback</p>
          <h1 className="mt-4 text-4xl font-semibold tracking-tight text-neutral-950">Share event feedback.</h1>
          <p className="mt-4 text-sm leading-6 text-neutral-600">
            Feedback becomes available after staff marks the event completed. Use the contact number on the booking to continue.
          </p>
        </div>
        <div className="mt-8">
          <TrackingPanel initialRef={decodeURIComponent(token)} />
        </div>
      </section>
      <PublicFooter />
    </main>
  );
}
