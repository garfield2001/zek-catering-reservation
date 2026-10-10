import { Link } from "@inertiajs/react";
import AdminLayout from "../../../layouts/admin-layout";
import { PaginationControls } from "../../../components/ui/pagination";
import { EmptyState } from "../../../features/catalog/components/empty-state";
import { packagePaths } from "../../../features/packages/paths";
import type { PackageProduct } from "../../../features/packages/types";
import type { Pagination } from "../../../types";

export default function PackageIndex({
    products,
    canManage,
}: {
    products: Pagination<PackageProduct>;
    canManage: boolean;
}) {
    return (
        <AdminLayout
            title="Package drafts"
            description="Four package families. Drafts are administrative only and are not customer-bookable."
        >
            {canManage && (
                <Link
                    href={packagePaths.index + "/create"}
                    className="mb-5 inline-block rounded-lg bg-emerald-800 px-4 py-2 text-sm font-semibold text-white"
                >
                    Create package product
                </Link>
            )}
            {products.data.length === 0 ? (
                <EmptyState
                    title="No package drafts"
                    description="Create a product, then add a priced variant and selection slots."
                />
            ) : (
                <div className="grid gap-4 sm:grid-cols-2">
                    {products.data.map((product) => (
                        <Link
                            key={product.id}
                            href={packagePaths.product(product.id)}
                            className="rounded-xl border border-stone-200 bg-white p-5 hover:border-emerald-700"
                        >
                            <h2 className="font-semibold">{product.name}</h2>
                            <p className="mt-1 text-sm text-stone-600">
                                {product.family?.name} ·{" "}
                                {product.variants_count ?? 0} variants ·{" "}
                                {product.active
                                    ? "Active owner"
                                    : "Inactive owner"}
                            </p>
                            <p className="mt-2 text-xs text-amber-800">
                                Draft only
                            </p>
                        </Link>
                    ))}
                </div>
            )}
            <PaginationControls {...products} />
        </AdminLayout>
    );
}
