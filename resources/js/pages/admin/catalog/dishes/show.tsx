import { Link } from "@inertiajs/react";
import AdminLayout from "../../../../layouts/admin-layout";
import { FoodImage } from "../../../../components/ui/food-image";
import {
    FoodUsage,
    type FoodUsageRow,
} from "../../../../features/catalog/components/food-usage";
import { StatusBadge } from "../../../../features/catalog/components/status-badge";
import { catalogPaths } from "../../../../features/catalog/paths";
import type { FoodItem } from "../../../../features/catalog/types";

export default function DishShow({
    dish,
    canManage,
    usedIn,
}: {
    dish: FoodItem;
    canManage: boolean;
    usedIn: FoodUsageRow[];
}) {
    const available = dish.active && !!dish.category?.active;
    return (
        <AdminLayout title={dish.name} description="Food Catalog">
            <Link
                href="/admin/food-catalog"
                className="mb-5 inline-block text-emerald-800 underline"
            >
                Back to Food Catalog
            </Link>
            <div className="grid items-start gap-6 md:grid-cols-[16rem_1fr]">
                <FoodImage src={dish.image_url} name={dish.name} portrait />
                <section className="space-y-4">
                    <div className="flex flex-wrap items-center gap-3">
                        <StatusBadge active={available} />
                        <span>{dish.category?.name}</span>
                        {canManage && (
                            <Link
                                className="text-emerald-800 underline"
                                href={catalogPaths.dish(dish.id) + "/edit"}
                            >
                                Edit Food
                            </Link>
                        )}
                    </div>
                    {dish.description && (
                        <p className="whitespace-pre-wrap">
                            {dish.description}
                        </p>
                    )}
                    <p className="text-sm text-slate-600">
                        {available
                            ? "Available to all active package builders. Each product independently chooses its foods."
                            : "Unavailable for new selections. Both the food and its category must be active."}
                    </p>
                    <h2 className="text-lg font-semibold">Used In</h2>
                    <FoodUsage rows={usedIn} />
                </section>
            </div>
        </AdminLayout>
    );
}
