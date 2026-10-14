import { Link, router, usePage } from "@inertiajs/react";
import { useState } from "react";
import AdminLayout from "../../../layouts/admin-layout";
import { ConfirmDialog } from "../../../components/ui/confirm-dialog";
import { ListingDetails } from "../../../features/listings/components/listing-details";
import type { ListingDetailsProps } from "../../../features/listings/types";

export default function ListingShow({
    listing,
    snapshot,
    version,
    image_url,
    availability,
    draft_id,
    history,
    canManage,
    canPublish,
    canHistory,
    canLifecycle,
}: ListingDetailsProps) {
    const [confirm, setConfirm] = useState<"publish" | "archive" | null>(null),
        [busy, setBusy] = useState(false);
    const { errors } = usePage().props;
    return (
        <AdminLayout
            title="Item Details"
            description={snapshot.service.name}
            section="services"
        >
            {Object.entries(errors).map(([key, error]) => (
                <p key={key} role="alert" className="mb-3 text-red-700">
                    {String(error)}
                </p>
            ))}
            <p className="mb-4 rounded-lg bg-emerald-50 p-3 text-sm">
                {version.status === "published"
                    ? "Published Version"
                    : version.status === "draft"
                      ? "Draft Preview — not published"
                      : "Abandoned Draft"}{" "}
                {version.version_number}
                {version.published_at &&
                    ` · Published ${new Date(version.published_at).toLocaleDateString()}`}
            </p>
            <div className="mb-5 flex flex-wrap gap-4 text-sm">
                <Link
                    href={`/admin/add-on-services/${listing.family_id}`}
                    className="underline"
                >
                    Back to Service
                </Link>
                {canManage && !listing.archived_at && (
                    <Link
                        href={`/admin/items/${listing.id}/edit`}
                        className="text-emerald-800 underline"
                    >
                        Edit Item
                    </Link>
                )}
                {draft_id && version.status === "published" && (
                    <Link
                        href={`/admin/items/${listing.id}?version=${draft_id}`}
                        className="underline"
                    >
                        Preview Draft Changes
                    </Link>
                )}
                {listing.current_version_id &&
                    version.id !== listing.current_version_id && (
                        <Link
                            href={`/admin/items/${listing.id}`}
                            className="underline"
                        >
                            View Current Publication
                        </Link>
                    )}
            </div>
            <ListingDetails snapshot={snapshot} imageUrl={image_url} />
            {version.status === "draft" && (
                <section className="my-5 rounded-xl border bg-white p-5">
                    <h2 className="font-semibold">Publication Review</h2>
                    <p className="my-3 text-sm">
                        Review the saved price, purchase quantities, food and
                        size above. Confirming publishes this exact Draft.
                    </p>
                    {canPublish && listing.active && !listing.archived_at ? (
                        <button
                            className="rounded-lg bg-emerald-800 px-4 py-2 text-white"
                            onClick={() => setConfirm("publish")}
                        >
                            Publish Item
                        </button>
                    ) : (
                        <p className="text-sm">
                            Ask an authorized publisher to review this Draft.
                            The item and its catalog sources must be enabled.
                        </p>
                    )}
                </section>
            )}
            <section className="my-5 space-y-3 rounded-xl border bg-white p-5">
                <h2 className="font-semibold">Availability</h2>
                <p className="text-sm">
                    {availability.available
                        ? "This published item is available."
                        : availability.reasons.join(" ")}
                </p>
                {canLifecycle && !listing.archived_at && (
                    <div className="flex gap-4 text-sm">
                        <button
                            className="underline"
                            onClick={() =>
                                router.post(
                                    `/admin/items/${listing.id}/availability`,
                                    {
                                        operation: listing.active
                                            ? "disable"
                                            : "enable",
                                    },
                                )
                            }
                        >
                            {listing.active ? "Disable Item" : "Enable Item"}
                        </button>
                        <button
                            className="text-red-700 underline"
                            onClick={() => setConfirm("archive")}
                        >
                            Archive Item
                        </button>
                    </div>
                )}
            </section>
            {canHistory && (
                <section className="space-y-3 rounded-xl border bg-white p-5">
                    <h2 className="font-semibold">Publication History</h2>
                    {!history.length && (
                        <p className="text-sm">No published revisions yet.</p>
                    )}
                    {history.map((v) => (
                        <div
                            key={v.id}
                            className="flex flex-wrap gap-4 text-sm"
                        >
                            <Link
                                className="underline"
                                href={`/admin/items/${listing.id}?version=${v.id}`}
                            >
                                {v.status === "published"
                                    ? "Published Version"
                                    : "Abandoned Draft"}{" "}
                                {v.version_number}
                            </Link>
                            {canManage &&
                                !draft_id &&
                                !listing.archived_at &&
                                v.status === "published" && (
                                    <Link
                                        as="button"
                                        method="post"
                                        className="text-emerald-800 underline"
                                        href={`/admin/items/${listing.id}/versions/${v.id}/revise`}
                                    >
                                        Create Draft from Version{" "}
                                        {v.version_number}
                                    </Link>
                                )}
                        </div>
                    ))}
                </section>
            )}
            <ConfirmDialog
                open={confirm !== null}
                onClose={() => setConfirm(null)}
                title={
                    confirm === "publish" ? "Publish Item?" : "Archive Item?"
                }
                description={
                    confirm === "publish"
                        ? "Confirm the saved configuration above. This publication will be immutable and replace the current version."
                        : "This permanently retires the item. Publication history is retained."
                }
                confirmLabel={
                    confirm === "publish"
                        ? "Confirm Publication"
                        : "Confirm Archive"
                }
                destructive={confirm === "archive"}
                busy={busy}
                onConfirm={() =>
                    router.post(
                        confirm === "publish"
                            ? `/admin/items/${listing.id}/versions/${version.id}/publish`
                            : `/admin/items/${listing.id}/availability`,
                        confirm === "publish"
                            ? {
                                  draft_revision: version.draft_revision,
                                  confirm: true,
                              }
                            : { operation: "archive", confirm: true },
                        {
                            onStart: () => setBusy(true),
                            onFinish: () => setBusy(false),
                            onSuccess: () => setConfirm(null),
                        },
                    )
                }
            />
        </AdminLayout>
    );
}
