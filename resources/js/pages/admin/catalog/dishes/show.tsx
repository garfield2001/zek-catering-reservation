import { Link } from "@inertiajs/react";
import AdminLayout from "../../../../layouts/admin-layout";
import { EmptyState } from "../../../../features/catalog/components/empty-state";
import { StatusBadge } from "../../../../features/catalog/components/status-badge";
import { catalogPaths } from "../../../../features/catalog/paths";
import type { Dish } from "../../../../features/catalog/types";
export default function DishShow({
    dish,
    canManage,
}: {
    dish: Dish;
    canManage: boolean;
}) {
    const activeFamilies =
        dish.eligible_families?.filter((family) => family.pivot?.active) ?? [];
    return (
        <AdminLayout title={dish.name} description={"Food item · " + dish.code}>
            <div className="mb-6 flex flex-wrap items-center gap-3">
                <StatusBadge active={dish.active} />
                <span className="text-sm text-stone-600">
                    {dish.category?.name}
                </span>
                {canManage && (
                    <Link
                        className="text-sm text-emerald-800 underline"
                        href={catalogPaths.dish(dish.id) + "/edit"}
                    >
                        Edit dish and eligibility
                    </Link>
                )}
            </div>
            {dish.description && (
                <p className="mb-6 max-w-2xl whitespace-pre-wrap text-stone-700">
                    {dish.description}
                </p>
            )}
            <section className="mb-8 rounded-xl border border-stone-200 bg-white p-5">
                <h2 className="font-semibold">Eligible collections</h2>
                <p className="mt-2 text-sm text-stone-600">
                    {activeFamilies.length
                        ? activeFamilies
                              .map((family) => family.name)
                              .join(" · ")
                        : "None assigned"}
                </p>
            </section>
            <div className="mb-4 flex items-center justify-between">
                <h2 className="text-lg font-semibold">Offerings</h2>
                {canManage && (
                    <Link
                        className="text-sm text-emerald-800 underline"
                        href={catalogPaths.dish(dish.id) + "/offerings/create"}
                    >
                        Add offering
                    </Link>
                )}
            </div>
            {!dish.offerings?.length ? (
                <EmptyState
                    title="No offerings yet"
                    description="Create a collection-specific preparation, then define its serving options."
                />
            ) : (
                <div className="grid gap-4 sm:grid-cols-2">
                    {dish.offerings.map((offering) => (
                        <Link
                            key={offering.id}
                            href={catalogPaths.offering(dish.id, offering.id)}
                            className="rounded-xl border border-stone-200 bg-white p-5 hover:border-emerald-700"
                        >
                            <div className="flex justify-between gap-3">
                                <h3 className="font-medium">{offering.name}</h3>
                                <StatusBadge active={offering.active} />
                            </div>
                            <p className="mt-2 text-sm text-stone-600">
                                {offering.family?.name} ·{" "}
                                {offering.options?.length ?? 0} serving options
                            </p>
                        </Link>
                    ))}
                </div>
            )}
        </AdminLayout>
    );
}
