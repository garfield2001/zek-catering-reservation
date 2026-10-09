import { Link } from "@inertiajs/react";
import AdminLayout from "../../../../layouts/admin-layout";
import { PaginationControls } from "../../../../components/ui/pagination";
import { EmptyState } from "../../../../features/catalog/components/empty-state";
import { StatusBadge } from "../../../../features/catalog/components/status-badge";
import { catalogPaths } from "../../../../features/catalog/paths";
import type { Category } from "../../../../features/catalog/types";
import type { Pagination } from "../../../../types";
export default function CategoryIndex({
    categories,
    canManage,
}: {
    categories: Pagination<Category>;
    canManage: boolean;
}) {
    return (
        <AdminLayout
            title="Culinary categories"
            description="These classify dishes for preparation and future selection rules."
        >
            {canManage && (
                <Link
                    className="mb-5 inline-block rounded-lg bg-emerald-800 px-4 py-2 text-sm font-semibold text-white"
                    href={catalogPaths.categories + "/create"}
                >
                    Create category
                </Link>
            )}
            {categories.data.length === 0 ? (
                <EmptyState
                    title="No categories yet"
                    description="Add a category to organize canonical dishes."
                />
            ) : (
                <div className="overflow-x-auto rounded-xl border border-stone-200 bg-white">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-stone-100">
                            <tr>
                                {["Category", "Order", "Dishes", "Status"].map(
                                    (label) => (
                                        <th className="px-5 py-3" key={label}>
                                            {label}
                                        </th>
                                    ),
                                )}
                            </tr>
                        </thead>
                        <tbody>
                            {categories.data.map((category) => (
                                <tr
                                    key={category.id}
                                    className="border-t border-stone-100"
                                >
                                    <td className="px-5 py-4">
                                        <Link
                                            className="font-medium text-emerald-800 underline"
                                            href={catalogPaths.category(
                                                category.id,
                                            )}
                                        >
                                            {category.name}
                                        </Link>
                                        <div className="text-xs text-stone-500">
                                            {category.code}
                                        </div>
                                    </td>
                                    <td className="px-5 py-4">
                                        {category.sort_order}
                                    </td>
                                    <td className="px-5 py-4">
                                        {category.dishes_count}
                                    </td>
                                    <td className="px-5 py-4">
                                        <StatusBadge active={category.active} />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
            <PaginationControls {...categories} />
        </AdminLayout>
    );
}
