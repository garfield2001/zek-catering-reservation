import { Link, useForm } from "@inertiajs/react";
import AdminLayout from "../../../layouts/admin-layout";
import { Button } from "../../../components/ui/button";
import { ConfigurationSummary } from "../../../features/packages/components/configuration-summary";
import { configurationDiff } from "../../../features/packages/configuration-diff";
import { packagePaths } from "../../../features/packages/paths";
import type {
    PackageProduct,
    PackageVariant,
    PublishedPackageSnapshot,
    VariantVersion,
} from "../../../features/packages/types";

export default function PublicationReview({
    product,
    variant,
    version,
    snapshot,
    previousSnapshot,
    blockingErrors,
    warnings,
}: {
    product: PackageProduct;
    variant: PackageVariant;
    version: VariantVersion;
    snapshot: PublishedPackageSnapshot | null;
    previousSnapshot: PublishedPackageSnapshot | null;
    blockingErrors: string[];
    warnings: string[];
}) {
    const base = packagePaths.version(product.id, variant.id, version.id);
    const form = useForm({
        draft_revision: version.draft_revision,
        confirm: false,
    });
    return (
        <AdminLayout
            title="Review publication"
            description={`${product.name} · ${variant.name} · Version ${version.version_number}`}
        >
            <Link href={base} className="text-sm text-emerald-800 underline">
                Back to draft
            </Link>
            {blockingErrors.length > 0 && (
                <section
                    role="alert"
                    className="my-5 rounded-lg bg-red-50 p-4 text-sm text-red-800"
                >
                    <h2 className="font-semibold">Resolve before publishing</h2>
                    <ul className="list-inside list-disc">
                        {blockingErrors.map((error, index) => (
                            <li key={index}>{error}</li>
                        ))}
                    </ul>
                </section>
            )}
            {warnings.length > 0 && (
                <section className="my-5 rounded-lg bg-amber-50 p-4 text-sm text-amber-900">
                    <h2 className="font-semibold">Review these details</h2>
                    {warnings.map((warning, index) => (
                        <p key={index}>{warning}</p>
                    ))}
                </section>
            )}
            {snapshot && (
                <>
                    <div className="my-6">
                        <ConfigurationSummary snapshot={snapshot} />
                    </div>
                    {previousSnapshot && (
                        <section className="my-5 rounded-xl border border-stone-200 bg-white p-5 text-sm">
                            <h2 className="font-semibold">
                                Compared with the current published version
                            </h2>
                            <ul className="list-inside list-disc">
                                {configurationDiff(
                                    previousSnapshot,
                                    snapshot,
                                ).map((change, index) => (
                                    <li key={index}>{change}</li>
                                ))}
                            </ul>
                        </section>
                    )}
                    <form
                        className="my-6 space-y-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post(base + "/publish");
                        }}
                    >
                        <label className="flex items-start gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={form.data.confirm}
                                onChange={(event) =>
                                    form.setData(
                                        "confirm",
                                        event.target.checked,
                                    )
                                }
                            />
                            I reviewed this configuration and confirm
                            publication.
                        </label>
                        {Object.values(form.errors).map((error, index) => (
                            <p
                                key={index}
                                role="alert"
                                className="text-sm text-red-700"
                            >
                                {error}
                            </p>
                        ))}
                        <Button
                            disabled={!form.data.confirm || form.processing}
                        >
                            Publish Version {version.version_number}
                        </Button>
                    </form>
                </>
            )}
        </AdminLayout>
    );
}
