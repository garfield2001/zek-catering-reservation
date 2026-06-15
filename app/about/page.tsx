import Image from "next/image";
import { PublicFooter, PublicNav } from "@/components/public-nav";

export default function AboutPage() {
  return (
    <main className="bg-neutral-50">
      <PublicNav />
      <section className="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 lg:grid-cols-[0.9fr_1.1fr] lg:px-8">
        <div>
          <p className="text-sm font-medium uppercase tracking-[0.2em] text-neutral-500">About</p>
          <h1 className="mt-4 text-4xl font-semibold tracking-tight text-neutral-950">
            Built for hosts who care about calm execution.
          </h1>
          <p className="mt-5 text-sm leading-6 text-neutral-600">
            Zek Catering is being shaped as a modern reservation and operations system for a catering business:
            public inquiry on the customer side, structured fulfillment on the admin side.
          </p>
        </div>
        <Image
          src="https://images.unsplash.com/photo-1555244162-803834f70033?auto=format&fit=crop&w=1400&q=80"
          alt="Catering team preparing an event"
          width={1400}
          height={1000}
          className="h-[520px] w-full rounded-md object-cover grayscale"
        />
      </section>
      <PublicFooter />
    </main>
  );
}
