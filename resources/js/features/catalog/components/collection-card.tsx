import { Link } from "@inertiajs/react";
import { Archive } from "lucide-react";
import type { Collection } from "../types";
import { CollectionOrderingDefaultForm } from "./collection-ordering-default-form";
import { CollectionPresentationForm } from "./collection-presentation-form";

export const behaviorNames: Record<Collection["behavior_type"], string> = {
    main_package: "Main catering packages",
    package: "Package-based add-on",
    packed_meal: "Packed meals",
    direct_sale: "Direct-sale items",
    fixed_bundle: "Fixed bundles",
};

export function CollectionCard({
    family,
    canManage,
    canManageDefaults,
    onArchive,
}: {
    family: Collection;
    canManage: boolean;
    canManageDefaults: boolean;
    onArchive: () => void;
}) {
    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        <h2 className="text-lg font-semibold">{family.name}</h2>
                        <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">
                            {family.is_addon ? "Add-on" : "Primary"}
                        </span>
                        <span
                            className={`rounded-full px-2.5 py-1 text-xs font-medium ${family.active ? "bg-emerald-100 text-emerald-800" : "bg-amber-100 text-amber-800"}`}
                        >
                            {family.active ? "Enabled" : "Disabled"}
                        </span>
                    </div>
                    <p className="mt-1 text-sm text-slate-600">
                        {behaviorNames[family.behavior_type]}
                    </p>
                    {family.description && (
                        <p className="mt-3 max-w-2xl text-sm text-slate-700">
                            {family.description}
                        </p>
                    )}
                </div>
                <div className="flex items-center gap-3">
                    <CollectionPresentationForm
                        family={family}
                        canManage={canManage}
                    />
                    {canManage && (
                        <button
                            type="button"
                            onClick={onArchive}
                            className="inline-flex items-center gap-1 text-sm font-medium text-rose-700 hover:underline"
                        >
                            <Archive size={15} aria-hidden="true" /> Archive
                        </button>
                    )}
                </div>
            </div>
            <div className="mt-4 flex flex-wrap gap-4 border-t border-slate-100 pt-4 text-sm">
                {family.behavior_type !== "direct_sale" ? (
                    <Link
                        href="/admin/packages/products"
                        className="font-semibold text-emerald-800 hover:underline"
                    >
                        Manage package drafts →
                    </Link>
                ) : (
                    <span className="text-amber-800">
                        Direct-sale listing manager is scheduled for a later
                        phase.
                    </span>
                )}
                <Link
                    href="/admin/catalog/dishes"
                    className="font-semibold text-emerald-800 hover:underline"
                >
                    Manage eligible food items →
                </Link>
            </div>
            {canManageDefaults && (
                <details className="mt-4 border-t border-slate-100 pt-4">
                    <summary className="cursor-pointer text-sm font-semibold text-slate-700">
                        Ordering defaults
                    </summary>
                    <div className="mt-3">
                        <CollectionOrderingDefaultForm family={family} />
                    </div>
                </details>
            )}
        </section>
    );
}
