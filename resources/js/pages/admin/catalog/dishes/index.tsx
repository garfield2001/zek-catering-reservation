import { Link } from "@inertiajs/react";
import AdminLayout from "../../../../layouts/admin-layout";
import { PaginationControls } from "../../../../components/ui/pagination";
import { DishSearch } from "../../../../features/catalog/components/dish-search";
import { EmptyState } from "../../../../features/catalog/components/empty-state";
import { StatusBadge } from "../../../../features/catalog/components/status-badge";
import { catalogPaths } from "../../../../features/catalog/paths";
import type { Dish } from "../../../../features/catalog/types";
import type { Pagination } from "../../../../types";
export default function DishIndex({
    dishes,
    filters,
    canManage,
}: {
    dishes: Pagination<Dish>;
    filters: { q: string };
    canManage: boolean;
}) {
    return (
        <AdminLayout
            title="Food items"
            description="Keep one record for each food or beverage, then choose which collections may use it."
        >
            {canManage && (
                <Link
                    className="mb-5 inline-block rounded-lg bg-emerald-800 px-4 py-2 text-sm font-semibold text-white"
                    href={catalogPaths.dishes + "/create"}
                >
                    Add food item
                </Link>
            )}
            <DishSearch initial={filters.q} />
            {dishes.data.length === 0 ? (
                <EmptyState
                    title="No food items found"
                    description={
                        filters.q
                            ? "Try a different name or code."
                            : "Add a food category first, then create a food item."
                    }
                />
            ) : (
                <div className="overflow-x-auto rounded-xl border border-stone-200 bg-white">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-stone-100">
                            <tr>
                                {[
                                    "Food item",
                                    "Category",
                                    "Offerings",
                                    "Status",
                                ].map((label) => (
                                    <th className="px-5 py-3" key={label}>
                                        {label}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {dishes.data.map((dish) => (
                                <tr
                                    key={dish.id}
                                    className="border-t border-stone-100"
                                >
                                    <td className="px-5 py-4">
                                        <Link
                                            className="font-medium text-emerald-800 underline"
                                            href={catalogPaths.dish(dish.id)}
                                        >
                                            {dish.name}
                                        </Link>
                                        <div className="text-xs text-stone-500">
                                            {dish.code}
                                        </div>
                                    </td>
                                    <td className="px-5 py-4">
                                        {dish.category?.name}
                                    </td>
                                    <td className="px-5 py-4">
                                        {dish.offerings_count}
                                    </td>
                                    <td className="px-5 py-4">
                                        <StatusBadge active={dish.active} />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
            <PaginationControls {...dishes} />
        </AdminLayout>
    );
}
