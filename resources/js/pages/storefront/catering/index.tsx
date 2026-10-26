import { OfferingCard } from "../../../features/storefront/components/offering-card";
import type { PublicPackage } from "../../../features/storefront/types";
import StorefrontLayout from "../../../layouts/storefront-layout";

export default function CateringIndex({
    packages,
    selectionCount,
}: {
    packages: PublicPackage[];
    selectionCount: number;
}) {
    return (
        <StorefrontLayout
            title="Catering Packages"
            selectionCount={selectionCount}
        >
            <section className="bg-emerald-950 py-14 text-white">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <p className="text-xs font-bold uppercase tracking-[0.16em] text-amber-300">
                        Catering Packages
                    </p>
                    <h1 className="mt-3 font-serif text-4xl font-bold">
                        Choose a menu made for your gathering.
                    </h1>
                    <p className="mt-4 max-w-2xl leading-7 text-emerald-100">
                        Review currently available packages and create a
                        planning selection with the foods your guests will
                        enjoy.
                    </p>
                </div>
            </section>
            <section className="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                {packages.length ? (
                    <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                        {packages.map((offering) => (
                            <OfferingCard
                                key={offering.id}
                                package={offering}
                                href={`/catering-packages/${offering.id}`}
                            />
                        ))}
                    </div>
                ) : (
                    <div className="rounded-2xl border border-dashed border-stone-300 bg-white p-8 text-stone-600">
                        No catering packages are available to browse right now.
                    </div>
                )}
            </section>
        </StorefrontLayout>
    );
}
