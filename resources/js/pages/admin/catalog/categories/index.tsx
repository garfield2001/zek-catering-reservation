import { Link, router } from "@inertiajs/react";
import { Plus } from "lucide-react";
import { useState } from "react";
import AdminLayout from "../../../../layouts/admin-layout";
import { Modal } from "../../../../components/ui/modal";
import { PaginationControls } from "../../../../components/ui/pagination";
import { CategoryForm } from "../../../../features/catalog/components/category-form";
import { EmptyState } from "../../../../features/catalog/components/empty-state";
import { StatusBadge } from "../../../../features/catalog/components/status-badge";
import { catalogPaths } from "../../../../features/catalog/paths";
import type { FoodCategory } from "../../../../features/catalog/types";
import type { Pagination } from "../../../../types";
export default function CategoryIndex({
    categories,
    canManage,
}: {
    categories: Pagination<FoodCategory>;
    canManage: boolean;
}) {
    const [creating, setCreating] = useState(false);
    return (
        <AdminLayout
            title="Food categories"
            description="Group food items for browsing and package selection rules."
        >
            {canManage && (
                <button
                    type="button"
                    onClick={() => setCreating(true)}
                    className="mb-5 inline-flex items-center gap-2 rounded-lg bg-emerald-800 px-4 py-2 text-sm font-semibold text-white"
                >
                    <Plus size={17} aria-hidden="true" /> Add category
                </button>
            )}
            {categories.data.length === 0 ? (
                <EmptyState
                    title="No categories yet"
                    description="Add a category to organize food items."
                />
            ) : (
                <div className="overflow-x-auto rounded-xl border border-stone-200 bg-white">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-stone-100">
                            <tr>
                                {[
                                    "Category",
                                    "Order",
                                    "Food items",
                                    "Status",
                                ].map((label) => (
                                    <th className="px-5 py-3" key={label}>
                                        {label}
                                    </th>
                                ))}
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
                                    </td>
                                    <td className="px-5 py-4">
                                        {canManage ? (
                                            <div className="flex gap-2">
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        router.post(
                                                            `${catalogPaths.category(category.id)}/move`,
                                                            { direction: "up" },
                                                        )
                                                    }
                                                    className="text-emerald-800 underline"
                                                    aria-label={`Move ${category.name} up`}
                                                >
                                                    Up
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        router.post(
                                                            `${catalogPaths.category(category.id)}/move`,
                                                            {
                                                                direction:
                                                                    "down",
                                                            },
                                                        )
                                                    }
                                                    className="text-emerald-800 underline"
                                                    aria-label={`Move ${category.name} down`}
                                                >
                                                    Down
                                                </button>
                                            </div>
                                        ) : (
                                            category.sort_order
                                        )}
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
            <Modal
                open={creating}
                onClose={() => setCreating(false)}
                title="Add food category"
            >
                <CategoryForm category={null} />
            </Modal>
        </AdminLayout>
    );
}
