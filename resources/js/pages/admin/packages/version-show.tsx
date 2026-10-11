import { Link, router } from "@inertiajs/react";
import AdminLayout from "../../../layouts/admin-layout";
import { EmptyState } from "../../../features/catalog/components/empty-state";
import { PreviewBuilder } from "../../../features/packages/components/preview-builder";
import { packagePaths } from "../../../features/packages/paths";
import { ConfigurationSummary } from "../../../features/packages/components/configuration-summary";
import type {
    VariantVersion,
    PackageProduct,
    PackageVariant,
} from "../../../features/packages/types";

export default function VersionShow({
    product,
    variant,
    version,
    canManage,
    canPrice,
    canPublish,
    canRevise,
}: {
    product: PackageProduct;
    variant: PackageVariant;
    version: VariantVersion;
    canManage: boolean;
    canPrice: boolean;
    canPublish: boolean;
    canRevise: boolean;
}) {
    const base = packagePaths.version(product.id, variant.id, version.id);
    return (
        <AdminLayout
            title={`${variant.name} — ${version.status} Version ${version.version_number}`}
            description={`${product.family?.name} · ${version.price_basis} · ${version.status === "draft" ? "not customer-available" : "commercial history"}`}
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
                {canPublish && (
                    <Link
                        href={packagePaths.review(
                            product.id,
                            variant.id,
                            version.id,
                        )}
                        className="text-emerald-800 underline"
                    >
                        Review and publish
                    </Link>
                )}
                {canRevise && (
                    <Link
                        as="button"
                        method="post"
                        href={base + "/revise"}
                        className="text-emerald-800 underline"
                    >
                        Restore as a new draft
                    </Link>
                )}
                {canManage && (
                    <Link
                        as="button"
                        method="post"
                        href={base + "/abandon"}
                        data={{
                            draft_revision: version.draft_revision,
                            confirm: true,
                        }}
                        onBefore={() =>
                            window.confirm(
                                "Abandon this draft? This cannot be undone; published history remains unchanged.",
                            )
                        }
                        className="text-red-700 underline"
                    >
                        Abandon draft
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
                        Add selection group
                    </Link>
                )}
            </div>
            {version.status === "draft" && (
                <p className="mb-5 rounded-lg bg-amber-50 p-3 text-sm text-amber-950">
                    This is an editable draft, not a published price or
                    quotation.
                </p>
            )}
            {version.status !== "draft" &&
                (version.published_snapshot ? (
                    <ConfigurationSummary
                        snapshot={version.published_snapshot}
                    />
                ) : (
                    <p className="rounded-lg bg-stone-100 p-3 text-sm">
                        This abandoned draft is read-only.
                    </p>
                ))}
            {version.status === "draft" && (
                <>
                    {variant.description && (
                        <p className="mb-5 whitespace-pre-wrap text-sm text-stone-700">
                            {variant.description}
                        </p>
                    )}
                    {!version.groups?.length ? (
                        <EmptyState
                            title="No selection groups"
                            description="Add included items and customer choices before a valid draft price preview is possible."
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
                                                {group.mode === "fixed"
                                                    ? "Included Items"
                                                    : "Customer Choices"}{" "}
                                                ·
                                                {group.slot_kind.replaceAll(
                                                    "_",
                                                    " ",
                                                )}{" "}
                                                · choose {group.minimum_count}–
                                                {group.maximum_count} ·{" "}
                                                {group.options?.length ?? 0}{" "}
                                                allowed serving options
                                            </p>
                                        </div>
                                        {canManage && (
                                            <div className="flex gap-3 text-sm">
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        router.post(
                                                            `${packagePaths.group(product.id, variant.id, version.id, group.id)}/move`,
                                                            {
                                                                direction: "up",
                                                                draft_revision:
                                                                    version.draft_revision,
                                                            },
                                                        )
                                                    }
                                                    className="text-emerald-800 underline"
                                                    aria-label={`Move ${group.name} up`}
                                                >
                                                    Up
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        router.post(
                                                            `${packagePaths.group(product.id, variant.id, version.id, group.id)}/move`,
                                                            {
                                                                direction:
                                                                    "down",
                                                                draft_revision:
                                                                    version.draft_revision,
                                                            },
                                                        )
                                                    }
                                                    className="text-emerald-800 underline"
                                                    aria-label={`Move ${group.name} down`}
                                                >
                                                    Down
                                                </button>
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
                                                    data={{
                                                        draft_revision:
                                                            version.draft_revision,
                                                    }}
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
                </>
            )}
        </AdminLayout>
    );
}
