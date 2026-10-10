import { Link } from "@inertiajs/react";
import AdminLayout from "../../../layouts/admin-layout";
import { EmptyState } from "../../../features/catalog/components/empty-state";
import { packagePaths } from "../../../features/packages/paths";
import type { PackageProduct } from "../../../features/packages/types";

export default function ProductShow({
    product,
    canManage,
    canPrice,
}: {
    product: PackageProduct;
    canManage: boolean;
    canPrice: boolean;
}) {
    return (
        <AdminLayout
            title={product.name}
            description={`${product.family?.name} · Administrative draft product`}
        >
            <div className="mb-5 flex flex-wrap gap-4 text-sm">
                {canManage && (
                    <Link
                        href={packagePaths.product(product.id) + "/edit"}
                        className="text-emerald-800 underline"
                    >
                        Edit product
                    </Link>
                )}
                {canManage && canPrice && (
                    <Link
                        href={packagePaths.variantCreate(product.id)}
                        className="text-emerald-800 underline"
                    >
                        Add variant draft
                    </Link>
                )}
            </div>
            {product.description && (
                <p className="mb-6 whitespace-pre-wrap text-stone-700">
                    {product.description}
                </p>
            )}
            {!product.variants?.length ? (
                <EmptyState
                    title="No variants yet"
                    description="Create a draft variant to configure pricing and selection groups."
                />
            ) : (
                <div className="grid gap-3 sm:grid-cols-2">
                    {product.variants.map((variant) => (
                        <div
                            key={variant.id}
                            className="rounded-xl border border-stone-200 bg-white p-5"
                        >
                            <h2 className="font-semibold">{variant.name}</h2>
                            {variant.description && (
                                <p className="mt-1 text-sm text-stone-700">
                                    {variant.description}
                                </p>
                            )}
                            <p className="text-sm text-stone-600">
                                {variant.code} ·{" "}
                                {variant.active
                                    ? "Active owner"
                                    : "Inactive owner"}
                            </p>
                            {variant.versions?.map((version) => (
                                <Link
                                    key={version.id}
                                    href={packagePaths.version(
                                        product.id,
                                        variant.id,
                                        version.id,
                                    )}
                                    className="mt-3 block text-sm text-emerald-800 underline"
                                >
                                    Open draft version {version.version_number}
                                </Link>
                            ))}
                        </div>
                    ))}
                </div>
            )}
        </AdminLayout>
    );
}
