import AdminLayout from "../../../layouts/admin-layout";
import { Link, useForm } from "@inertiajs/react";
import { Button } from "../../../components/ui/button";
import { MainCateringWorkflowPage } from "../../../features/packages/components/main-catering-workflow-nav";
import { PackageReview } from "../../../features/packages/components/package-review";
import type { PackageEditorProps } from "../../../features/packages/components/complete-package-form";
import {
    initialMenu,
    menuSummary,
} from "../../../features/packages/menu-state";
import { minorToPesoInput } from "../../../features/packages/money";

export default function CateringReview({
    family,
    product,
    version,
    categories,
    foods,
    measurementUnits,
    canPublish,
    blockingErrors,
}: PackageEditorProps & {
    blockingErrors: string[];
    snapshot: unknown | null;
}) {
    if (!product || !version)
        throw new Error("A Draft is required for review.");
    const menu = initialMenu(version);
    const data = {
        ...menu,
        food_sizes: {},
        serving_default: null,
        presentation: null,
        name: product.name,
        description: product.description ?? "",
        active: product.active,
        family_id: family.id,
        version_id: version.id,
        draft_revision: version.draft_revision,
        base_price_pesos: minorToPesoInput(version.base_price_minor),
        price_basis: version.price_basis,
        selection_policy: version.selection_policy,
        minimum_quantity: String(version.minimum_quantity),
        quantity_step: String(version.quantity_step),
        maximum_quantity: version.maximum_quantity?.toString() ?? "",
        estimated_capacity: "",
    } as const;
    const summary = menuSummary(data, foods);
    const publish = useForm({
        draft_revision: version.draft_revision,
        confirm: true,
    });

    return (
        <AdminLayout
            title={`Review · ${product.name}`}
            description={family.name}
            section="catering"
        >
            <MainCateringWorkflowPage
                family={family}
                product={product}
                current="review"
                title="Review & Save"
                description="Review the complete persisted Draft. Publication remains an explicit, authorized action."
            >
                <section className="space-y-5 rounded-2xl border bg-white p-5 sm:p-6">
                    {blockingErrors.length > 0 ? (
                        <div
                            role="alert"
                            className="rounded-xl bg-amber-50 p-4 text-sm text-amber-950"
                        >
                            <p className="font-semibold">
                                This Draft needs attention before publication.
                            </p>
                            <ul className="mt-2 list-disc space-y-1 pl-5">
                                {blockingErrors.map((error) => (
                                    <li key={error}>{error}</li>
                                ))}
                            </ul>
                        </div>
                    ) : (
                        <p className="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-950">
                            This Draft has passed the current server-side
                            configuration checks. Publishing will still
                            revalidate it inside the transaction.
                        </p>
                    )}
                    <PackageReview
                        data={data}
                        main
                        summary={summary}
                        categories={categories}
                        measurementUnits={measurementUnits}
                    />
                    <div className="flex flex-wrap gap-3 border-t pt-5">
                        <Link
                            className="text-sm text-emerald-800 underline"
                            href={`/admin/catering-packages/${product.id}/details`}
                        >
                            Edit Details
                        </Link>
                        <Link
                            className="text-sm text-emerald-800 underline"
                            href={`/admin/catering-packages/${product.id}/pricing`}
                        >
                            Edit Pricing
                        </Link>
                        <Link
                            className="text-sm text-emerald-800 underline"
                            href={`/admin/catering-packages/${product.id}/menu`}
                        >
                            Edit Menu
                        </Link>
                    </div>
                    <div className="flex flex-wrap justify-end gap-3 pt-2">
                        <Link
                            className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700"
                            href={`/admin/catering-packages/${product.id}`}
                        >
                            Save Draft & Return
                        </Link>
                        <Button
                            disabled={
                                !canPublish ||
                                blockingErrors.length > 0 ||
                                publish.processing
                            }
                            onClick={() =>
                                publish.post(
                                    `/admin/packages/products/${product.id}/versions/${version.id}/publish`,
                                )
                            }
                        >
                            Save & Publish
                        </Button>
                    </div>
                    {!canPublish && (
                        <p className="text-sm text-amber-800">
                            You may review this Draft, but publishing requires
                            the package publication permission.
                        </p>
                    )}
                </section>
            </MainCateringWorkflowPage>
        </AdminLayout>
    );
}
