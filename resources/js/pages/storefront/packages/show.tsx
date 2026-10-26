import { Link, router } from "@inertiajs/react";
import { ArrowLeft, CheckCircle2, Users } from "lucide-react";
import { PackageConfigurator } from "../../../features/storefront/components/package-configurator";
import { formatPesos } from "../../../features/packages/money";
import type { PublicPackage } from "../../../features/storefront/types";
import StorefrontLayout from "../../../layouts/storefront-layout";

export default function StorefrontPackageShow({
    offering,
    selectionCount,
}: {
    offering: PublicPackage;
    selectionCount: number;
}) {
    const fixed =
        offering.menu?.food_items.filter((food) => food.is_fixed) ?? [];
    return (
        <StorefrontLayout title={offering.name} selectionCount={selectionCount}>
            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <Link
                    href="/catering-packages"
                    className="inline-flex items-center gap-2 text-sm font-semibold text-emerald-800 hover:underline"
                >
                    <ArrowLeft size={16} /> All Catering Packages
                </Link>
                <div className="mt-6 grid gap-8 lg:grid-cols-[.85fr_1.15fr]">
                    <section>
                        <p className="text-xs font-bold uppercase tracking-[0.16em] text-amber-700">
                            {offering.family.name}
                        </p>
                        <h1 className="mt-2 font-serif text-4xl font-bold text-emerald-950">
                            {offering.name}
                        </h1>
                        {offering.description && (
                            <p className="mt-4 max-w-xl text-lg leading-8 text-stone-600">
                                {offering.description}
                            </p>
                        )}
                        <div className="mt-7 rounded-2xl bg-amber-50 p-5">
                            <span className="text-2xl font-bold text-emerald-950">
                                {formatPesos(offering.terms.base_price_minor)}
                            </span>
                            <span className="ml-2 text-sm text-stone-600">
                                {offering.terms.price_basis === "per_pax"
                                    ? "per guest"
                                    : offering.terms.price_basis === "per_pack"
                                      ? "per pack"
                                      : "per set"}
                            </span>
                            <p className="mt-3 flex items-center gap-2 text-sm text-stone-700">
                                <Users size={16} /> Minimum{" "}
                                {offering.terms.minimum_quantity}
                                {offering.terms.estimated_capacity
                                    ? ` · estimated for up to ${offering.terms.estimated_capacity}`
                                    : ""}
                            </p>
                        </div>
                        {fixed.length > 0 && (
                            <div className="mt-7">
                                <h2 className="text-lg font-bold text-emerald-950">
                                    Included Items
                                </h2>
                                <ul className="mt-3 space-y-2 text-sm text-stone-700">
                                    {fixed.map((food) => (
                                        <li
                                            key={food.id}
                                            className="flex items-center gap-2"
                                        >
                                            <CheckCircle2
                                                size={16}
                                                className="text-emerald-700"
                                            />{" "}
                                            {food.name}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                        <p className="mt-8 text-sm leading-6 text-stone-500">
                            Published details and prices shown here are for
                            planning. Submitting a selection does not make a
                            reservation.
                        </p>
                    </section>
                    <PackageConfigurator
                        offering={offering}
                        previewUrl={`/catering-packages/${offering.id}/preview`}
                        selectionUrl={`/catering-packages/${offering.id}/selection`}
                        onSelected={() =>
                            router.reload({ only: ["selectionCount"] })
                        }
                    />
                </div>
            </div>
        </StorefrontLayout>
    );
}
