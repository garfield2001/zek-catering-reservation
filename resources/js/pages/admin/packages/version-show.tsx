import { Link } from "@inertiajs/react";
import AdminLayout from "../../../layouts/admin-layout";
import { EmptyState } from "../../../features/catalog/components/empty-state";
import { PreviewBuilder } from "../../../features/packages/components/preview-builder";
import { packagePaths } from "../../../features/packages/paths";
import type {
    DraftVersion,
    PackageProduct,
    PackageVariant,
} from "../../../features/packages/types";

export default function VersionShow({
    product,
    variant,
    version,
    canManage,
    canPrice,
}: {
    product: PackageProduct;
    variant: PackageVariant;
    version: DraftVersion;
    canManage: boolean;
    canPrice: boolean;
}) {
    const base = packagePaths.version(product.id, variant.id, version.id);
    return (
        <AdminLayout
            title={`${variant.name} — draft`}
            description={`${product.family?.name} · ${version.price_basis} · draft-only, never customer-bookable`}
        >
            <div className="mb-5 flex flex-wrap gap-4 text-sm">
                {canManage && canPrice && (
                    <Link
                        href={base + "/edit"}
                        className="text-emerald-800 underline"
                    >
                        Edit price and quantity rules
                    </Link>
                )}
                {canManage && (
                    <Link
                        href={
                            packagePaths.group(
                                product.id,
                                variant.id,
                                version.id,
                            ) + "/create"
                        }
                        className="text-emerald-800 underline"
                    >
                        Add selection slot
                    </Link>
                )}
            </div>
            <p className="mb-5 rounded-lg bg-amber-50 p-3 text-sm text-amber-950">
                This is an editable draft. Phase 4 must publish and freeze it
                before any customer-facing use.
            </p>
            {variant.description && (
                <p className="mb-5 whitespace-pre-wrap text-sm text-stone-700">
                    {variant.description}
                </p>
            )}
            {!version.groups?.length ? (
                <EmptyState
                    title="No selection slots"
                    description="Add fixed inclusions and selectable slots before a valid preview is possible."
                />
            ) : (
                <div className="mb-7 space-y-3">
                    {version.groups.map((group) => (
                        <div
                            key={group.id}
                            className="rounded-xl border border-stone-200 bg-white p-4"
                        >
                            <div className="flex justify-between gap-3">
                                <div>
                                    <h2 className="font-semibold">
                                        {group.name}
                                    </h2>
                                    <p className="text-xs text-stone-600">
                                        {group.slot_kind} · {group.mode} ·
                                        choose {group.minimum_count}–
                                        {group.maximum_count} ·{" "}
                                        {group.options?.length ?? 0} allowed
                                        options
                                    </p>
                                </div>
                                {canManage && (
                                    <div className="flex gap-3 text-sm">
                                        <Link
                                            href={
                                                packagePaths.group(
                                                    product.id,
                                                    variant.id,
                                                    version.id,
                                                    group.id,
                                                ) + "/edit"
                                            }
                                            className="text-emerald-800 underline"
                                        >
                                            Edit
                                        </Link>
                                        <Link
                                            as="button"
                                            method="delete"
                                            href={packagePaths.group(
                                                product.id,
                                                variant.id,
                                                version.id,
                                                group.id,
                                            )}
                                            onBefore={() =>
                                                window.confirm(
                                                    `Remove ${group.name} from this draft?`,
                                                )
                                            }
                                            className="text-red-700 underline"
                                        >
                                            Remove
                                        </Link>
                                    </div>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            )}
            <PreviewBuilder
                product={product}
                variant={variant}
                version={version}
            />
        </AdminLayout>
    );
}
