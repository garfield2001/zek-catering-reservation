import { Link } from "@inertiajs/react";
import { useState } from "react";
import AdminLayout from "../../../../layouts/admin-layout";
import { Modal } from "../../../../components/ui/modal";
import { PaginationControls } from "../../../../components/ui/pagination";
import { CategoryForm } from "../../../../features/catalog/components/category-form";
import { EmptyState } from "../../../../features/catalog/components/empty-state";
import { StatusBadge } from "../../../../features/catalog/components/status-badge";
import { catalogPaths } from "../../../../features/catalog/paths";
import type {
    FoodCategory,
    FoodItem,
} from "../../../../features/catalog/types";
import type { Pagination } from "../../../../types";
export default function CategoryShow({
    category,
    dishes,
    canManage,
}: {
    category: FoodCategory;
    dishes: Pagination<FoodItem>;
    canManage: boolean;
}) {
    const [editing, setEditing] = useState(false);
    return (
        <AdminLayout
            title={category.name}
            description={"Food category · " + category.code}
        >
            <div className="mb-6 flex items-center gap-4">
                <StatusBadge active={category.active} />
                <span className="text-sm text-stone-500">
                    Display order {category.sort_order}
                </span>
                {canManage && (
                    <button
                        type="button"
                        onClick={() => setEditing(true)}
                        className="text-sm text-emerald-800 underline"
                    >
                        Edit category
                    </button>
                )}
            </div>
            <h2 className="mb-3 text-lg font-semibold">Food items</h2>
            {dishes.data.length === 0 ? (
                <EmptyState
                    title="No food items in this category"
                    description="Food items can be added from the catalog."
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
            <Modal
                open={editing}
                onClose={() => setEditing(false)}
                title={`Edit ${category.name}`}
            >
                <CategoryForm category={category} />
            </Modal>
        </AdminLayout>
    );
}
