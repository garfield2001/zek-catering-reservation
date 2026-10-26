import { Link, router } from "@inertiajs/react";
import { ArrowLeft } from "lucide-react";
import { ListingCard } from "../../../features/storefront/components/listing-card";
import { OfferingCard } from "../../../features/storefront/components/offering-card";
import { PackageConfigurator } from "../../../features/storefront/components/package-configurator";
import type {
    PublicListing,
    PublicPackage,
} from "../../../features/storefront/types";
import StorefrontLayout from "../../../layouts/storefront-layout";

export default function ServiceShow({
    service,
    packages,
    listings,
    selectionCount,
}: {
    service: { id: number; name: string; description: string | null };
    packages: PublicPackage[];
    listings: PublicListing[];
    selectionCount: number;
}) {
    return (
        <StorefrontLayout title={service.name} selectionCount={selectionCount}>
            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <Link
                    href="/add-on-services"
                    className="inline-flex items-center gap-2 text-sm font-semibold text-emerald-800 hover:underline"
                >
                    <ArrowLeft size={16} /> All Add-on Services
                </Link>
                <header className="mt-7 max-w-3xl">
                    <p className="text-xs font-bold uppercase tracking-[0.16em] text-amber-700">
                        Add-on Service
                    </p>
                    <h1 className="mt-2 font-serif text-4xl font-bold text-emerald-950">
                        {service.name}
                    </h1>
                    {service.description && (
                        <p className="mt-4 text-lg leading-8 text-stone-600">
                            {service.description}
                        </p>
                    )}
                </header>
                {packages.length > 0 && (
                    <section className="mt-12">
                        <h2 className="text-2xl font-bold text-emerald-950">
                            Service Packages
                        </h2>
                        <div className="mt-5 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                            {packages.map((offering) => (
                                <OfferingCard
                                    key={offering.id}
                                    package={offering}
                                    href={`#package-${offering.id}`}
                                />
                            ))}
                        </div>
                        <div className="mt-6 space-y-6">
                            {packages.map((offering) => (
                                <div
                                    key={offering.id}
                                    id={`package-${offering.id}`}
                                    className="scroll-mt-6"
                                >
                                    <h3 className="mb-3 text-xl font-bold text-emerald-950">
                                        Configure {offering.name}
                                    </h3>
                                    <div className="max-w-3xl">
                                        <PackageConfigurator
                                            offering={offering}
                                            previewUrl={`/add-on-services/${service.id}/packages/${offering.id}/preview`}
                                            selectionUrl={`/add-on-services/${service.id}/packages/${offering.id}/selection`}
                                            onSelected={() =>
                                                router.reload({
                                                    only: ["selectionCount"],
                                                })
                                            }
                                        />
                                    </div>
                                </div>
                            ))}
                        </div>
                    </section>
                )}
                {listings.length > 0 && (
                    <section className="mt-12">
                        <h2 className="text-2xl font-bold text-emerald-950">
                            Individual Items
                        </h2>
                        <p className="mt-2 text-sm text-stone-600">
                            These are priced and selected independently.
                        </p>
                        <div className="mt-5 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                            {listings.map((listing) => (
                                <ListingCard
                                    key={listing.id}
                                    listing={listing}
                                    selectionUrl={`/add-on-services/${service.id}/foods/${listing.id}/selection`}
                                    onSelected={() =>
                                        router.reload({
                                            only: ["selectionCount"],
                                        })
                                    }
                                />
                            ))}
                        </div>
                    </section>
                )}
            </div>
        </StorefrontLayout>
    );
}
