import { Link, useForm } from "@inertiajs/react";
import { useState } from "react";
import { ConfirmDialog } from "../../../components/ui/confirm-dialog";
import AdminLayout from "../../../layouts/admin-layout";
import { Button } from "../../../components/ui/button";
import { ConfigurationSummary } from "../../../features/packages/components/configuration-summary";
import { packagePaths } from "../../../features/packages/paths";
import type {
    PackageProduct,
    PackageVersion,
    PublishedPackageSnapshot,
} from "../../../features/packages/types";

export default function PublicationReview({
    product,
    version,
    snapshot,
    blockingErrors,
    warnings,
}: {
    product: PackageProduct;
    version: PackageVersion;
    snapshot: PublishedPackageSnapshot | null;
    blockingErrors: string[];
    warnings: string[];
}) {
    const [confirming, setConfirming] = useState(false);
    const base = packagePaths.version(product.id, version.id);
    const form = useForm({
        draft_revision: version.draft_revision,
        confirm: false,
    });
    return (
        <AdminLayout
            section={
                product.family?.behavior_type === "main_package"
                    ? "catering"
                    : "services"
            }
            title="Review publication"
            description={`${product.name} · Version ${version.version_number}`}
        >
            <Link href={base} className="text-sm text-emerald-800 underline">
                Back to Package
            </Link>
            {blockingErrors.length > 0 && (
                <section
                    role="alert"
                    className="my-5 rounded bg-red-50 p-4 text-sm text-red-800"
                >
                    <h2 className="font-semibold">Resolve before publishing</h2>
                    {blockingErrors.map((error, index) => (
                        <p key={index}>{error}</p>
                    ))}
                </section>
            )}
            {warnings.map((warning, index) => (
                <p key={index} className="my-3 rounded bg-amber-50 p-3 text-sm">
                    {warning}
                </p>
            ))}
            {snapshot && (
                <div className="my-5">
                    <ConfigurationSummary snapshot={snapshot} />
                </div>
            )}
            {Object.values(form.errors).map((error, i) => (
                <p key={i} role="alert" className="my-3 text-red-700">
                    {error}
                </p>
            ))}
            {snapshot && (
                <form
                    className="my-6 space-y-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        setConfirming(true);
                    }}
                >
                    <label className="flex gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.confirm}
                            onChange={(event) =>
                                form.setData("confirm", event.target.checked)
                            }
                        />
                        I reviewed this configuration and confirm publication.
                    </label>
                    <Button disabled={!form.data.confirm || form.processing}>
                        {product.current_version_id
                            ? "Publish Changes"
                            : "Publish Package"}
                    </Button>
                </form>
            )}
            <ConfirmDialog
                open={confirming}
                onClose={() => setConfirming(false)}
                title={
                    product.current_version_id
                        ? "Publish these changes?"
                        : "Publish this package?"
                }
                description="The saved menu, price and purchase rules will become the current publication. Published terms cannot be edited; future changes use a new draft."
                confirmLabel="Confirm Publication"
                busy={form.processing}
                onConfirm={() =>
                    form.post(`${base}/publish`, {
                        onSuccess: () => setConfirming(false),
                    })
                }
            />
        </AdminLayout>
    );
}
