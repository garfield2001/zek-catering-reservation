import { Link } from "@inertiajs/react";
import AdminLayout from "../../../../layouts/admin-layout";
import { OptionList } from "../../../../features/catalog/components/option-list";
import { StatusBadge } from "../../../../features/catalog/components/status-badge";
import { catalogPaths } from "../../../../features/catalog/paths";
import type { Dish, Offering } from "../../../../features/catalog/types";
export default function OfferingShow({
    dish,
    offering,
    canManage,
}: {
    dish: Dish;
    offering: Offering;
    canManage: boolean;
}) {
    return (
        <AdminLayout
            title={offering.name}
            description={dish.name + " · " + offering.family?.name}
        >
            <div className="mb-6 flex items-center gap-4">
                <StatusBadge active={offering.active} />
                <span className="text-sm text-stone-500">{offering.code}</span>
                {canManage && (
                    <Link
                        className="text-sm text-emerald-800 underline"
                        href={
                            catalogPaths.offering(dish.id, offering.id) +
                            "/edit"
                        }
                    >
                        Edit offering
                    </Link>
                )}
            </div>
            {offering.description && (
                <p className="mb-6 whitespace-pre-wrap text-stone-700">
                    {offering.description}
                </p>
            )}
            <div className="mb-4 flex items-center justify-between">
                <h2 className="text-lg font-semibold">Serving options</h2>
                {canManage && !!offering.options?.length && (
                    <Link
                        className="text-sm text-emerald-800 underline"
                        href={
                            catalogPaths.offering(dish.id, offering.id) +
                            "/options/create"
                        }
                    >
                        Add serving option
                    </Link>
                )}
            </div>
            <OptionList
                dishId={dish.id}
                offeringId={offering.id}
                options={offering.options ?? []}
                canManage={canManage}
            />
        </AdminLayout>
    );
}
