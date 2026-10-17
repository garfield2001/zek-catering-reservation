import { PackageFilters } from "../../../features/packages/components/package-filters";
import { PackageEditorButton } from "../../../features/packages/components/package-editor-modal";
import { Link, router, usePage } from "@inertiajs/react";
import { useState } from "react";
import AdminLayout from "../../../layouts/admin-layout";
import type { Collection } from "../../../features/catalog/types";
import type { PackageProduct } from "../../../features/packages/types";
import { PackageCards } from "../../../features/packages/components/package-cards";
import { CollectionPresentationForm } from "../../../features/catalog/components/collection-presentation-form";
import { serviceBehaviors } from "../../../features/catalog/components/collection-create-form";
import {
    ListingCards,
    type ListingCard,
} from "../../../features/listings/components/listing-cards";
import { ConfirmDialog } from "../../../components/ui/confirm-dialog";
import { ListingEditorButton } from "../../../features/listings/components/listing-editor-modal";
export default function ServiceShow({
    family,
    products,
    filters,
    canManage,
    canPackages,
    canCatalog,
    canArchive,
    listings,
    canListings,
    canPrice,
    canHistory,
}: {
    family: Collection;
    products: PackageProduct[];
    filters: { q?: string; status?: string };
    canManage: boolean;
    canPackages: boolean;
    canCatalog: boolean;
    canArchive: boolean;
    listings: ListingCard[];
    canListings: boolean;
    canPrice: boolean;
    canHistory: boolean;
}) {
    const [archive, setArchive] = useState(false);
    const [archiving, setArchiving] = useState(false);
    const { errors } = usePage().props;
    return (
        <AdminLayout
            title={family.name}
            description={
                serviceBehaviors.find((b) => b.value === family.behavior_type)
                    ?.detail
            }
            section="services"
        >
            <div className="mb-5 flex items-center gap-4">
                <span className="text-sm">
                    {family.archived_at
                        ? "Archived"
                        : family.active
                          ? "Enabled"
                          : "Disabled"}
                </span>
                {!family.archived_at && (
                    <CollectionPresentationForm
                        family={family}
                        canManage={canManage}
                    />
                )}
            </div>
            {Object.values(errors).map((error, i) => (
                <p key={i} role="alert" className="text-red-700">
                    {String(error)}
                </p>
            ))}
            {family.behavior_type !== "direct_sale" && (
                <PackageFilters
                    url={`/admin/add-on-services/${family.id}`}
                    initial={filters}
                />
            )}
            {family.behavior_type === "direct_sale" ? (
                <section className="mb-5 space-y-5">
                    {canListings && family.active && !family.archived_at ? (
                        <ListingEditorButton
                            url={`/admin/add-on-services/${family.id}/items/create`}
                        >
                            Create Item
                        </ListingEditorButton>
                    ) : (
                        <p className="rounded-lg bg-amber-50 p-3 text-sm">
                            Creating an item requires listing editing and
                            pricing permission, and an active service. Ask an
                            Administrator if you need access.
                        </p>
                    )}
                    <ListingCards listings={listings} />
                    {!listings.length && (
                        <p className="rounded-xl border bg-white p-6 text-sm">
                            No individual items available to view. Prepare
                            available foods below, then create an independently
                            priced item.
                        </p>
                    )}
                </section>
            ) : (
                <>
                    <div className="mb-5 flex flex-wrap gap-4">
                        {canPackages &&
                            canPrice &&
                            family.active &&
                            !family.archived_at && (
                                <PackageEditorButton
                                    url={`/admin/packages/editor?service=${family.id}`}
                                    className="rounded-lg bg-emerald-800 px-4 py-2 font-semibold text-white"
                                >
                                    Create Package
                                </PackageEditorButton>
                            )}
                    </div>
                    {(!canPackages ||
                        !canPrice ||
                        !family.active ||
                        family.archived_at) && (
                        <p className="mb-5 rounded-lg bg-amber-50 p-3 text-sm">
                            Creating a package requires editing and pricing
                            permission and an enabled, unarchived service. Ask
                            an Administrator to review access or service
                            availability.
                        </p>
                    )}
                    <PackageCards
                        products={products}
                        canManage={canPackages}
                        canHistory={canHistory}
                    />
                    {!products.length && (
                        <p className="mb-5 rounded-xl border bg-white p-6 text-sm">
                            No packages yet. Prepare your foods, then create a
                            package with its own price and inclusions.
                        </p>
                    )}
                </>
            )}
            {canCatalog && (
                <Link
                    className="my-5 inline-block text-sm text-emerald-800 underline"
                    href={`/admin/food-catalog?service=${family.id}`}
                >
                    Manage Foods & Serving Sizes
                </Link>
            )}
            {canArchive && !family.archived_at && (
                <button
                    onClick={() => setArchive(true)}
                    className="text-sm text-rose-700 underline"
                >
                    Archive Service
                </button>
            )}
            <ConfirmDialog
                open={archive}
                onClose={() => setArchive(false)}
                title="Archive Service?"
                description={`This permanently retires ${family.name}, prevents selection of all its packages and individual listings, and abandons their open Drafts. Child enabled flags, Food Items and immutable published history remain saved. The service cannot be restored.`}
                confirmLabel="Confirm Archive"
                busy={archiving}
                destructive
                onConfirm={() =>
                    router.post(
                        `/admin/catalog/families/${family.id}/archive`,
                        {},
                        {
                            onStart: () => setArchiving(true),
                            onFinish: () => setArchiving(false),
                            onSuccess: () => setArchive(false),
                        },
                    )
                }
            />
        </AdminLayout>
    );
}
