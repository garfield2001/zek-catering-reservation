import { Link, router } from "@inertiajs/react";
import AdminLayout from "../../../layouts/admin-layout";
import { EmptyState } from "../../../features/catalog/components/empty-state";
import { packagePaths } from "../../../features/packages/paths";
import type { PackageProduct } from "../../../features/packages/types";

export default function ProductShow({
    product,
    canManage,
    canPrice,
    canLifecycle,
    availabilityByVariant,
}: {
    product: PackageProduct;
    canManage: boolean;
    canPrice: boolean;
    canLifecycle: boolean;
    availabilityByVariant: Record<
        number,
        { available: boolean; reasons: string[] }
    >;
}) {
    return (
        <AdminLayout
            title={product.name}
            description={`${product.family?.name} · Package Product`}
        >
            <div className="mb-5 flex flex-wrap gap-4 text-sm">
                <Link
                    href={packagePaths.indexFor({
                        familyId: product.family_id,
                    })}
                    className="text-emerald-800 underline"
                >
                    Back to {product.family?.name} packages
                </Link>
                {canManage && !product.archived_at && (
                    <Link
                        href={packagePaths.product(product.id) + "/edit"}
                        className="text-emerald-800 underline"
                    >
                        Edit product
                    </Link>
                )}
                {canManage && canPrice && !product.archived_at && (
                    <Link
                        href={packagePaths.variantCreate(product.id)}
                        className="text-emerald-800 underline"
                    >
                        Add another variant
                    </Link>
                )}
                {canLifecycle && !product.archived_at && (
                    <>
                        <Link
                            as="button"
                            method="post"
                            href={
                                packagePaths.product(product.id) +
                                "/availability"
                            }
                            data={{
                                operation: product.active
                                    ? "disable"
                                    : "enable",
                                confirm: true,
                            }}
                            className="text-amber-800 underline"
                        >
                            {product.active
                                ? "Temporarily disable product"
                                : "Enable product"}
                        </Link>
                        <Link
                            as="button"
                            method="post"
                            href={
                                packagePaths.product(product.id) +
                                "/availability"
                            }
                            data={{ operation: "archive", confirm: true }}
                            onBefore={() =>
                                window.confirm(
                                    "Permanently archive this product? Active drafts will be abandoned.",
                                )
                            }
                            className="text-red-700 underline"
                        >
                            Archive product
                        </Link>
                    </>
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
                                {variant.active ? "Enabled" : "Disabled"}
                            </p>
                            {canManage && !variant.archived_at && (
                                <div className="mt-2 flex gap-3 text-xs">
                                    <button
                                        type="button"
                                        className="text-emerald-800 underline"
                                        aria-label={`Move ${variant.name} up`}
                                        onClick={() =>
                                            router.post(
                                                `${packagePaths.variants(product.id)}/${variant.id}/move`,
                                                { direction: "up" },
                                            )
                                        }
                                    >
                                        Move Up
                                    </button>
                                    <button
                                        type="button"
                                        className="text-emerald-800 underline"
                                        aria-label={`Move ${variant.name} down`}
                                        onClick={() =>
                                            router.post(
                                                `${packagePaths.variants(product.id)}/${variant.id}/move`,
                                                { direction: "down" },
                                            )
                                        }
                                    >
                                        Move Down
                                    </button>
                                </div>
                            )}
                            <p
                                className={
                                    availabilityByVariant[variant.id]?.available
                                        ? "text-sm text-emerald-800"
                                        : "text-sm text-amber-800"
                                }
                            >
                                {availabilityByVariant[variant.id]?.available
                                    ? "Currently available"
                                    : (availabilityByVariant[
                                          variant.id
                                      ]?.reasons.join(" ") ?? "Not available")}
                            </p>
                            {canLifecycle && !variant.archived_at && (
                                <div className="mt-2 flex flex-wrap gap-3 text-sm">
                                    <Link
                                        as="button"
                                        method="post"
                                        href={`${packagePaths.variants(product.id)}/${variant.id}/availability`}
                                        data={{
                                            operation: variant.active
                                                ? "disable"
                                                : "enable",
                                        }}
                                        className="text-amber-800 underline"
                                    >
                                        {variant.active
                                            ? "Disable variant"
                                            : "Enable variant"}
                                    </Link>
                                    <Link
                                        as="button"
                                        method="post"
                                        href={`${packagePaths.variants(product.id)}/${variant.id}/availability`}
                                        data={{
                                            operation: "archive",
                                            confirm: true,
                                        }}
                                        onBefore={() =>
                                            window.confirm(
                                                "Permanently archive this variant? Its active draft will be abandoned.",
                                            )
                                        }
                                        className="text-red-700 underline"
                                    >
                                        Archive variant
                                    </Link>
                                </div>
                            )}
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
                                    {version.status === "draft"
                                        ? "Configure draft"
                                        : `View ${version.status} version`}{" "}
                                    {version.version_number}
                                </Link>
                            ))}
                        </div>
                    ))}
                </div>
            )}
        </AdminLayout>
    );
}
