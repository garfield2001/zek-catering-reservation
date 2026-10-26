import { Link } from "@inertiajs/react";
import { ArrowRight } from "lucide-react";
import type { PublicService } from "../../../features/storefront/types";
import StorefrontLayout from "../../../layouts/storefront-layout";

export default function ServicesIndex({
    services,
    selectionCount,
}: {
    services: PublicService[];
    selectionCount: number;
}) {
    return (
        <StorefrontLayout
            title="Add-on Services"
            selectionCount={selectionCount}
        >
            <section className="bg-amber-50 py-14">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <p className="text-xs font-bold uppercase tracking-[0.16em] text-amber-700">
                        Add-on Services
                    </p>
                    <h1 className="mt-3 font-serif text-4xl font-bold text-emerald-950">
                        More ways to complete your spread.
                    </h1>
                    <p className="mt-4 max-w-2xl leading-7 text-stone-600">
                        Browse separately sold packages and individually priced
                        items. Each service has its own available options and
                        purchase rules.
                    </p>
                </div>
            </section>
            <section className="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    {services.map((service) => (
                        <Link
                            key={service.id}
                            href={`/add-on-services/${service.id}`}
                            className="rounded-2xl border border-stone-200 bg-white p-6 shadow-sm hover:border-emerald-300"
                        >
                            <h2 className="text-xl font-bold text-emerald-950">
                                {service.name}
                            </h2>
                            <p className="mt-2 min-h-12 text-sm leading-6 text-stone-600">
                                {service.description ||
                                    "See published choices for this service."}
                            </p>
                            <p className="mt-5 text-sm font-semibold text-emerald-800">
                                View service{" "}
                                <ArrowRight className="inline" size={16} />
                            </p>
                        </Link>
                    ))}
                </div>
                {services.length === 0 && (
                    <div className="rounded-2xl border border-dashed border-stone-300 bg-white p-8 text-stone-600">
                        No add-on services are available to browse right now.
                    </div>
                )}
            </section>
        </StorefrontLayout>
    );
}
