import { Link, router } from "@inertiajs/react";
import AdminLayout from "../../../layouts/admin-layout";
import { PaginationControls } from "../../../components/ui/pagination";
import { EmptyState } from "../../../features/catalog/components/empty-state";
import { packagePaths } from "../../../features/packages/paths";
import type { PackageProduct } from "../../../features/packages/types";
import type { Pagination } from "../../../types";

export default function PackageIndex({
    products,
    canManage,
    context,
}: {
    products: Pagination<PackageProduct>;
    canManage: boolean;
    context: {
        familyId: number | null;
        scope: "primary" | "addon" | null;
        label: string | null;
    };
}) {
    const createHref =
        context.familyId || context.scope
            ? `${packagePaths.index}/create?${new URLSearchParams({ ...(context.familyId ? { family_id: String(context.familyId) } : {}), ...(context.scope ? { scope: context.scope } : {}) }).toString()}`
            : `${packagePaths.index}/create`;
    return (
        <AdminLayout
            title={context.label ?? "Catering Packages"}
            description="Configure packages, meals and bundles across your collections. Publication and live availability are tracked per variant."
        >
            {canManage && (
                <Link
                    href={createHref}
                    className="mb-5 inline-block rounded-lg bg-emerald-800 px-4 py-2 text-sm font-semibold text-white"
                >
                    Create package product
                </Link>
            )}
            {products.data.length === 0 ? (
                <EmptyState
                    title="No package products"
                    description="Create a Package Product to get its initial variant and editable draft automatically."
                />
            ) : (
                <div className="grid gap-4 sm:grid-cols-2">
                    {products.data.map((product) => (
                        <div
                            key={product.id}
                            className="rounded-xl border border-stone-200 bg-white p-5 hover:border-emerald-700"
                        >
                            <h2 className="font-semibold">
                                <Link
                                    href={packagePaths.product(
                                        product.id,
                                        product.family_id,
                                    )}
                                    className="text-emerald-800 underline"
                                >
                                    {product.name}
                                </Link>
                            </h2>
                            <p className="mt-1 text-sm text-stone-600">
                                {product.family?.name} ·{" "}
                                {product.variants_count ?? 0} variants ·{" "}
                                {product.archived_at
                                    ? "Archived"
                                    : product.active
                                      ? "Enabled"
                                      : "Disabled"}
                            </p>
                            <p className="mt-2 text-xs text-amber-800">
                                Open to review variants and publication status
                            </p>
                            {canManage && !product.archived_at && (
                                <div className="mt-3 flex gap-3 text-xs">
                                    <button
                                        type="button"
                                        className="text-emerald-800 underline"
                                        aria-label={`Move ${product.name} up`}
                                        onClick={() =>
                                            router.post(
                                                `${packagePaths.product(product.id)}/move`,
                                                { direction: "up" },
                                            )
                                        }
                                    >
                                        Move Up
                                    </button>
                                    <button
                                        type="button"
                                        className="text-emerald-800 underline"
                                        aria-label={`Move ${product.name} down`}
                                        onClick={() =>
                                            router.post(
                                                `${packagePaths.product(product.id)}/move`,
                                                { direction: "down" },
                                            )
                                        }
                                    >
                                        Move Down
                                    </button>
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            )}
            <PaginationControls {...products} />
        </AdminLayout>
    );
}
