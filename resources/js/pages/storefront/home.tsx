import { Link } from "@inertiajs/react";
import { ArrowRight, CalendarDays, Utensils } from "lucide-react";
import { OfferingCard } from "../../features/storefront/components/offering-card";
import type {
    PublicPackage,
    PublicService,
} from "../../features/storefront/types";
import StorefrontLayout from "../../layouts/storefront-layout";

export default function StorefrontHome({
    catering,
    services,
    selectionCount,
}: {
    catering: PublicPackage[];
    services: PublicService[];
    selectionCount: number;
}) {
    return (
        <StorefrontLayout
            title="Catering for every gathering"
            selectionCount={selectionCount}
        >
            <section className="bg-emerald-950 text-white">
                <div className="mx-auto grid max-w-7xl gap-10 px-4 py-16 sm:px-6 md:py-24 lg:grid-cols-[1.2fr_.8fr] lg:px-8">
                    <div>
                        <p className="text-sm font-bold uppercase tracking-[0.2em] text-amber-300">
                            ZEK Catering
                        </p>
                        <h1 className="mt-4 max-w-3xl font-serif text-4xl font-bold leading-tight sm:text-6xl">
                            Food made for the moments that bring people
                            together.
                        </h1>
                        <p className="mt-6 max-w-2xl text-lg leading-8 text-emerald-100">
                            Explore our catering packages and add-on services,
                            then build a selection that fits your gathering.
                        </p>
                        <div className="mt-8 flex flex-wrap gap-3">
                            <Link
                                href="/catering-packages"
                                className="rounded-xl bg-amber-500 px-5 py-3 font-semibold text-stone-950 hover:bg-amber-400"
                            >
                                Explore Catering Packages
                            </Link>
                            <Link
                                href="/add-on-services"
                                className="rounded-xl border border-emerald-200 px-5 py-3 font-semibold hover:bg-white/10"
                            >
                                Browse Add-on Services
                            </Link>
                        </div>
                    </div>
                    <div className="rounded-2xl border border-emerald-800 bg-emerald-900/60 p-6">
                        <Utensils className="text-amber-300" size={34} />
                        <h2 className="mt-6 text-2xl font-bold">
                            Plan at your own pace
                        </h2>
                        <p className="mt-3 leading-7 text-emerald-100">
                            See current published package options and estimates.
                            Your selection stays in this browser while you
                            explore.
                        </p>
                        <div className="mt-7 border-t border-emerald-800 pt-5 text-sm text-emerald-100">
                            <CalendarDays className="mr-2 inline" size={17} />{" "}
                            Final availability is confirmed with our team.
                        </div>
                    </div>
                </div>
            </section>
            <section className="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
                <div className="flex items-end justify-between gap-4">
                    <div>
                        <p className="text-xs font-bold uppercase tracking-[0.16em] text-amber-700">
                            For your event
                        </p>
                        <h2 className="mt-2 text-3xl font-bold text-emerald-950">
                            Catering Packages
                        </h2>
                    </div>
                    <Link
                        href="/catering-packages"
                        className="hidden items-center gap-1 text-sm font-semibold text-emerald-800 hover:underline sm:inline-flex"
                    >
                        See all <ArrowRight size={16} />
                    </Link>
                </div>
                {catering.length ? (
                    <div className="mt-7 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        {catering.map((offering) => (
                            <OfferingCard
                                key={offering.id}
                                package={offering}
                                href={`/catering-packages/${offering.id}`}
                            />
                        ))}
                    </div>
                ) : (
                    <p className="mt-6 rounded-xl border border-dashed border-stone-300 p-6 text-stone-600">
                        Catering packages will be available here soon.
                    </p>
                )}
            </section>
            <section className="bg-amber-50">
                <div className="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
                    <p className="text-xs font-bold uppercase tracking-[0.16em] text-amber-700">
                        Complete the table
                    </p>
                    <h2 className="mt-2 text-3xl font-bold text-emerald-950">
                        Add-on Services
                    </h2>
                    <div className="mt-7 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {services.map((service) => (
                            <Link
                                key={service.id}
                                href={`/add-on-services/${service.id}`}
                                className="rounded-2xl border border-amber-100 bg-white p-5 hover:border-amber-300"
                            >
                                <h3 className="font-bold text-emerald-950">
                                    {service.name}
                                </h3>
                                <p className="mt-2 text-sm leading-6 text-stone-600">
                                    {service.description ||
                                        "Explore available options."}
                                </p>
                                <p className="mt-4 text-sm font-semibold text-emerald-800">
                                    Explore service{" "}
                                    <ArrowRight className="inline" size={15} />
                                </p>
                            </Link>
                        ))}
                    </div>
                    {services.length === 0 && (
                        <p className="mt-6 text-stone-600">
                            More services will be available soon.
                        </p>
                    )}
                </div>
            </section>
        </StorefrontLayout>
    );
}
