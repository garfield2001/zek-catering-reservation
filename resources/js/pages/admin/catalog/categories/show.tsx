import { Link } from "@inertiajs/react";
import AdminLayout from "../../../../layouts/admin-layout";
import { PaginationControls } from "../../../../components/ui/pagination";
import { EmptyState } from "../../../../features/catalog/components/empty-state";
import { StatusBadge } from "../../../../features/catalog/components/status-badge";
import { catalogPaths } from "../../../../features/catalog/paths";
import type { Category, Dish } from "../../../../features/catalog/types";
import type { Pagination } from "../../../../types";
export default function CategoryShow({
    category,
    dishes,
    canManage,
}: {
    category: Category;
    dishes: Pagination<Dish>;
    canManage: boolean;
}) {
    return (
        <AdminLayout
            title={category.name}
            description={"Culinary category · " + category.code}
        >
            <div className="mb-6 flex items-center gap-4">
                <StatusBadge active={category.active} />
                <span className="text-sm text-stone-500">
                    Display order {category.sort_order}
                </span>
                {canManage && (
                    <Link
                        className="text-sm text-emerald-800 underline"
                        href={catalogPaths.category(category.id) + "/edit"}
                    >
                        Edit category
                    </Link>
                )}
            </div>
            <h2 className="mb-3 text-lg font-semibold">Dishes</h2>
            {dishes.data.length === 0 ? (
                <EmptyState
                    title="No dishes in this category"
                    description="Dishes can be added from the master catalog."
                />
            ) : (
                <div className="rounded-xl border border-stone-200 bg-white">
                    {dishes.data.map((dish) => (
                        <Link
                            key={dish.id}
                            className="flex items-center justify-between border-b border-stone-100 px-5 py-4 text-sm hover:bg-stone-50"
                            href={catalogPaths.dish(dish.id)}
                        >
                            <span>{dish.name}</span>
                            <StatusBadge active={dish.active} />
                        </Link>
                    ))}
                </div>
            )}
            <PaginationControls {...dishes} />
        </AdminLayout>
    );
}
