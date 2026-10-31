import { useState } from "react";
import AdminLayout from "../../../layouts/admin-layout";
import { useForm } from "@inertiajs/react";
import { Field } from "../../../components/ui/field";
import { Button } from "../../../components/ui/button";
import { UnsavedChanges } from "../../../components/ui/unsaved-changes";
import type { Collection } from "../../../features/catalog/types";
import type { PackageProduct } from "../../../features/packages/types";
import { MainCateringWorkflowPage } from "../../../features/packages/components/main-catering-workflow-nav";

export default function CateringDetails({
    family,
    product,
}: {
    family: Collection;
    product: PackageProduct | null;
}) {
    const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false);
    const form = useForm({
        // Creation is server-scoped to the primary Collection. The existing
        // product route retains its family id solely for the immutable-family
        // guard in the shared save action.
        family_id: product ? family.id : undefined,
        name: product?.name ?? "",
        description: product?.description ?? "",
        active: product?.active ?? true,
        creation_token: product ? undefined : crypto.randomUUID(),
    });
    const update = <K extends keyof typeof form.data>(
        key: K,
        value: (typeof form.data)[K],
    ) => {
        setHasUnsavedChanges(true);
        form.setData((current) => ({ ...current, [key]: value }));
        form.clearErrors(key);
    };
    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.clearErrors();
        if (product) form.put(`/admin/catering-packages/${product.id}/details`);
        else form.post("/admin/catering-packages");
    };

    return (
        <AdminLayout
            title={product ? `Edit ${product.name}` : "Create Catering Package"}
            description={family.name}
            section="catering"
        >
            <MainCateringWorkflowPage
                family={family}
                product={product}
                current="details"
                title="Package Details"
                description="Start with the package identity. You can return to these details while the Draft remains editable."
            >
                <UnsavedChanges dirty={form.isDirty || hasUnsavedChanges} />
                <form
                    className="max-w-2xl space-y-5 rounded-2xl border bg-white p-5 sm:p-6"
                    onSubmit={submit}
                    noValidate
                >
                    <Field
                        id="package-name"
                        label="Package Name"
                        error={form.errors.name}
                    >
                        <input
                            id="package-name"
                            className={
                                form.errors.name ? "border-red-500" : undefined
                            }
                            required
                            maxLength={255}
                            value={form.data.name}
                            onChange={(event) =>
                                update("name", event.target.value)
                            }
                            onBlur={() => form.clearErrors("name")}
                        />
                    </Field>
                    <Field
                        id="package-description"
                        label="Description (optional)"
                        error={form.errors.description}
                    >
                        <textarea
                            id="package-description"
                            className={
                                form.errors.description
                                    ? "border-red-500"
                                    : undefined
                            }
                            maxLength={5000}
                            value={form.data.description}
                            onChange={(event) =>
                                update("description", event.target.value)
                            }
                            onBlur={() => form.clearErrors("description")}
                        />
                    </Field>
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.active}
                            onChange={(event) =>
                                update("active", event.target.checked)
                            }
                        />
                        Active package
                    </label>
                    <p className="rounded-xl bg-slate-50 p-3 text-sm text-slate-600">
                        Saving creates or updates this package and its initial
                        Draft only. Pricing and menu facts are saved on their
                        own pages and are never fabricated automatically.
                    </p>
                    <div className="flex flex-wrap justify-end gap-3 pt-2">
                        <Button disabled={form.processing}>
                            {product
                                ? "Save & Continue to Pricing"
                                : "Save Details & Continue"}
                        </Button>
                    </div>
                </form>
            </MainCateringWorkflowPage>
        </AdminLayout>
    );
}
