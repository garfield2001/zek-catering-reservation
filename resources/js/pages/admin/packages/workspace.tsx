import { Link, router, usePage } from "@inertiajs/react";
import { useState } from "react";
import AdminLayout from "../../../layouts/admin-layout";
import { ConfirmDialog } from "../../../components/ui/confirm-dialog";
import { PackageDetails } from "../../../features/packages/components/package-details";
import { PublicationHistory } from "../../../features/packages/components/publication-history";
import {
    CommercialHistory,
    type CommercialHistoryData,
} from "../../../features/catalog/components/commercial-history";
import type {
    PackageProduct,
    PackageVersion,
} from "../../../features/packages/types";

export default function PackageWorkspace({
    product,
    draft,
    history,
    commercialHistory,
    availability,
    canManage,
    canPrice,
    canPublish,
    canHistory,
    canLifecycle,
}: {
    product: PackageProduct;
    draft: Pick<
        PackageVersion,
        "id" | "status" | "draft_revision" | "version_number"
    > | null;
    history: PackageVersion[];
    commercialHistory: CommercialHistoryData | null;
    availability: { available: boolean; reasons: string[] };
    canManage: boolean;
    canPrice: boolean;
    canPublish: boolean;
    canHistory: boolean;
    canLifecycle: boolean;
}) {
    const main = product.family?.behavior_type === "main_package";
    const editable =
        canManage && !product.archived_at && !product.family?.archived_at;
    const [details, setDetails] = useState(false);
    const [archive, setArchive] = useState(false);
    const [archiving, setArchiving] = useState(false);
    const { errors } = usePage().props;
    return (
        <AdminLayout
            title={product.name}
            description={main ? "Catering Package" : product.family?.name}
            section={main ? "catering" : "services"}
        >
            <div className="mb-5 flex flex-wrap items-center gap-3">
                <span className="rounded-full bg-slate-100 px-3 py-1 text-sm">
                    {product.archived_at
                        ? "Archived"
                        : draft
                          ? "Package Draft"
                          : "Published"}
                </span>
                <span className="text-sm text-slate-600">
                    {availability.available
                        ? "Available · published"
                        : "Not currently available"}
                </span>
                {(!!product.current_version_id || editable) && (
                    <button
                        onClick={() => setDetails(true)}
                        className="text-sm text-emerald-800 underline"
                    >
                        View Details
                    </button>
                )}
            </div>
            {product.description && (
                <p className="mb-5 text-sm text-slate-600">
                    {product.description}
                </p>
            )}
            {Object.values(errors).map((error, i) => (
                <p
                    key={i}
                    role="alert"
                    className="mb-3 rounded bg-red-50 p-3 text-red-700"
                >
                    {String(error)}
                </p>
            ))}
            <section className="mb-6 rounded-xl border bg-white p-6">
                <h2 className="font-semibold">
                    {draft ? "Saved Draft" : "Published configuration"}
                </h2>
                <p className="mt-2 text-sm text-stone-600">
                    {draft
                        ? "The complete package has been saved. Review and confirm publication separately."
                        : "Editing creates a separate Draft when you save. The current publication remains unchanged."}
                </p>
                <div className="mt-4 flex flex-wrap gap-4">
                    {editable && (
                        <Link
                            className="text-sm text-emerald-800 underline"
                            href={`/admin/packages/products/${product.id}/edit`}
                        >
                            Edit Package
                        </Link>
                    )}
                    {editable && (
                        <Link
                            href={`/admin/packages/products/${product.id}/edit?step=1`}
                            className="text-sm text-emerald-800 underline"
                        >
                            Edit Menu
                        </Link>
                    )}
                    {editable && canPrice && (
                        <Link
                            href={`/admin/packages/products/${product.id}/edit?step=2`}
                            className="text-sm text-emerald-800 underline"
                        >
                            Edit Pricing & Quantity
                        </Link>
                    )}
                    {draft && canManage && (
                        <Link
                            className="text-sm text-emerald-800 underline"
                            href={`/admin/packages/products/${product.id}/versions/${draft.id}/customer-preview`}
                        >
                            Preview Customer Presentation
                        </Link>
                    )}
                    {draft &&
                        canPublish &&
                        product.active &&
                        product.family?.active &&
                        !product.archived_at &&
                        !product.family.archived_at && (
                            <Link
                                className="rounded-lg bg-emerald-800 px-4 py-2 text-sm text-white"
                                href={`/admin/packages/products/${product.id}/versions/${draft.id}/review`}
                            >
                                Review & Publish
                            </Link>
                        )}
                </div>
                {draft && !canPublish && (
                    <p className="mt-3 text-sm">
                        Ask an Administrator or a team member with publishing
                        permission to review this Draft.
                    </p>
                )}
            </section>
            {canHistory && (
                <section
                    id="history"
                    className="mb-6 rounded-2xl border bg-white p-6"
                >
                    <h2 className="mb-4 text-xl font-semibold">
                        Publication History
                    </h2>
                    <PublicationHistory
                        productId={product.id}
                        history={history}
                        canRevise={editable && !draft}
                    />
                    {commercialHistory && (
                        <CommercialHistory data={commercialHistory} />
                    )}
                </section>
            )}
            <section className="rounded-xl border bg-white p-6">
                <h2 className="font-semibold">Availability</h2>
                <p className="mt-2 text-sm">
                    {availability.available
                        ? "The current publication is available."
                        : availability.reasons.join(" ")}
                </p>
                {canLifecycle && !product.archived_at && (
                    <p className="mt-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
                        Disabling stops new selection of this package without
                        changing its Draft, published history or Food Item
                        statuses. Re-enable it when ready; its catalog sources
                        must also remain available. Archiving is permanent and
                        abandons its open Draft.
                    </p>
                )}
                {canLifecycle && !product.archived_at && (
                    <div className="mt-4 flex gap-5">
                        <button
                            className="text-sm text-emerald-800 underline"
                            onClick={() =>
                                router.post(
                                    `/admin/packages/products/${product.id}/availability`,
                                    {
                                        operation: product.active
                                            ? "disable"
                                            : "enable",
                                    },
                                    { preserveScroll: true },
                                )
                            }
                        >
                            {product.active
                                ? "Disable Package"
                                : "Enable Package"}
                        </button>
                        <button
                            onClick={() => setArchive(true)}
                            className="text-sm text-rose-700 underline"
                        >
                            Archive Package
                        </button>
                    </div>
                )}
            </section>
            <PackageDetails
                productId={product.id}
                open={details}
                onClose={() => setDetails(false)}
            />
            <ConfirmDialog
                open={archive}
                onClose={() => setArchive(false)}
                title="Archive Package?"
                description={`Archive ${product.name}? This is permanent and abandons its open Draft. Published history, Food Items and other packages remain unchanged.`}
                confirmLabel="Confirm Archive"
                busy={archiving}
                destructive
                onConfirm={() =>
                    router.post(
                        `/admin/packages/products/${product.id}/availability`,
                        { operation: "archive", confirm: true },
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
