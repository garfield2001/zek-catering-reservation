import { Link } from "@inertiajs/react";
import { ArrowRight, Users } from "lucide-react";
import { formatPesos } from "../../packages/money";
import type { PublicPackage } from "../types";

export function OfferingCard({
    package: offering,
    href,
}: {
    package: PublicPackage;
    href: string;
}) {
    const basis =
        offering.terms.price_basis === "per_pax"
            ? "per guest"
            : offering.terms.price_basis === "per_pack"
              ? "per pack"
              : "per set";
    return (
        <article className="flex h-full flex-col rounded-2xl border border-stone-200 bg-white p-6 shadow-sm">
            <p className="text-xs font-bold uppercase tracking-[0.16em] text-amber-700">
                {offering.family.name}
            </p>
            <h2 className="mt-2 text-xl font-bold text-emerald-950">
                {offering.name}
            </h2>
            {offering.description && (
                <p className="mt-2 text-sm leading-6 text-stone-600">
                    {offering.description}
                </p>
            )}
            <div className="mt-5 flex items-end justify-between gap-3 border-t border-stone-100 pt-4">
                <div>
                    <span className="block text-lg font-bold text-emerald-900">
                        {formatPesos(offering.terms.base_price_minor)}
                    </span>
                    <span className="text-xs text-stone-500">{basis}</span>
                </div>
                <span className="inline-flex items-center gap-1 text-xs text-stone-500">
                    <Users size={14} /> From {offering.terms.minimum_quantity}
                </span>
            </div>
            <Link
                href={href}
                className="mt-5 inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-800 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-900"
            >
                View package <ArrowRight size={16} />
            </Link>
        </article>
    );
}
