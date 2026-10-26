import { Link, useForm } from "@inertiajs/react";
import { Button } from "../../../components/ui/button";
import { Field } from "../../../components/ui/field";
import { UnsavedChanges } from "../../../components/ui/unsaved-changes";
import type { Collection } from "../types";
import { DeactivationImpact } from "./deactivation-impact";
import {
    initialServicePackage,
    ServicePackageFields,
    type ServicePackageInput,
} from "./service-package-fields";

type ServiceEditorData = {
    kind: "addon";
    behavior_type: "package" | "direct_sale";
    name: string;
    description: string;
    active: boolean;
    package_configuration?: ServicePackageInput;
};

export function ServiceEditorForm({ family }: { family: Collection | null }) {
    const editing = family !== null;
    const packaged = !editing || family.behavior_type !== "direct_sale";
    const form = useForm<ServiceEditorData>({
        kind: "addon",
        behavior_type:
            family?.behavior_type === "direct_sale" ? "direct_sale" : "package",
        name: family?.name ?? "",
        description: family?.description ?? "",
        active: family?.active ?? true,
        package_configuration: family?.package_format
            ? {
                  format: family.package_format,
                  quantity: family.default_presentation_quantity ?? 1,
                  measurement_unit_id:
                      family.default_presentation_unit_id ?? null,
              }
            : initialServicePackage(),
    });
    const packageConfigurationLocked = Boolean(
        family?.package_format && family.products_count,
    );
    const back = family
        ? `/admin/add-on-services/${family.id}`
        : "/admin/add-on-services";

    return (
        <form
            className="mx-auto max-w-4xl space-y-6"
            onSubmit={(event) => {
                event.preventDefault();
                form.transform((data) => ({
                    ...data,
                    package_configuration:
                        data.behavior_type === "direct_sale"
                            ? undefined
                            : data.package_configuration,
                }));
                if (family) form.put(`/admin/catalog/families/${family.id}`);
                else form.post("/admin/add-on-services");
            }}
        >
            <UnsavedChanges dirty={form.isDirty} />
            {Object.values(form.errors).map((error, index) => (
                <p
                    key={index}
                    role="alert"
                    className="rounded-lg bg-red-50 p-3 text-sm text-red-800"
                >
                    {error}
                </p>
            ))}
            <section className="space-y-4 rounded-2xl border bg-white p-5 sm:p-6">
                <h2 className="text-xl font-semibold">Service Details</h2>
                <Field
                    id="service-name"
                    label="Service name"
                    error={form.errors.name}
                >
                    <input
                        id="service-name"
                        required
                        maxLength={255}
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData("name", event.target.value)
                        }
                    />
                </Field>
                <Field
                    id="service-description"
                    label="Description (optional)"
                    error={form.errors.description}
                >
                    <textarea
                        id="service-description"
                        maxLength={5000}
                        value={form.data.description}
                        onChange={(event) =>
                            form.setData("description", event.target.value)
                        }
                    />
                </Field>
            </section>

            <section className="space-y-4 rounded-2xl border bg-white p-5 sm:p-6">
                <h2 className="text-xl font-semibold">Selling Configuration</h2>
                {editing ? (
                    <p className="rounded-lg bg-slate-50 p-3 text-sm text-slate-700">
                        This service is sold{" "}
                        {packaged ? "by Package" : "Individually"}. Its selling
                        method is preserved because changing it could invalidate
                        existing packages, listings, and publication history.
                    </p>
                ) : (
                    <fieldset>
                        <legend className="text-sm font-medium">
                            How will customers buy this service?
                        </legend>
                        <div className="mt-3 grid gap-3 sm:grid-cols-2">
                            <label
                                className={`rounded-xl border p-4 ${form.data.behavior_type === "package" ? "border-emerald-700 bg-emerald-50" : ""}`}
                            >
                                <input
                                    className="mr-2"
                                    type="radio"
                                    name="selling-method"
                                    checked={
                                        form.data.behavior_type === "package"
                                    }
                                    onChange={() =>
                                        form.setData("behavior_type", "package")
                                    }
                                />
                                <strong>Sold by Package</strong>
                                <span className="mt-1 block text-xs text-slate-600">
                                    Customers select from complete packages
                                    configured under this service.
                                </span>
                            </label>
                            <label
                                className={`rounded-xl border p-4 ${form.data.behavior_type === "direct_sale" ? "border-emerald-700 bg-emerald-50" : ""}`}
                            >
                                <input
                                    className="mr-2"
                                    type="radio"
                                    name="selling-method"
                                    checked={
                                        form.data.behavior_type ===
                                        "direct_sale"
                                    }
                                    onChange={() =>
                                        form.setData(
                                            "behavior_type",
                                            "direct_sale",
                                        )
                                    }
                                />
                                <strong>Sold Individually</strong>
                                <span className="mt-1 block text-xs text-slate-600">
                                    Customers choose separately priced Food
                                    Items.
                                </span>
                            </label>
                        </div>
                    </fieldset>
                )}
                {form.data.behavior_type !== "direct_sale" && (
                    <ServicePackageFields
                        value={
                            form.data.package_configuration ??
                            initialServicePackage()
                        }
                        onChange={(value) =>
                            form.setData("package_configuration", value)
                        }
                        locked={packageConfigurationLocked}
                    />
                )}
            </section>

            {editing && (
                <section className="space-y-3 rounded-2xl border bg-white p-5 sm:p-6">
                    <h2 className="text-xl font-semibold">Availability</h2>
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.active}
                            onChange={(event) =>
                                form.setData("active", event.target.checked)
                            }
                        />
                        Enabled for new configuration and customer selection
                    </label>
                    {!form.data.active && (
                        <DeactivationImpact kind="service" id={family.id} />
                    )}
                </section>
            )}

            <div className="flex flex-wrap justify-between gap-3 border-t pt-5">
                <Link
                    className="rounded-lg px-4 py-2 text-sm font-semibold text-emerald-800 hover:bg-emerald-50"
                    href={back}
                >
                    Back to {family ? "Service" : "Add-on Services"}
                </Link>
                <Button disabled={form.processing}>
                    {form.processing
                        ? "Saving…"
                        : family
                          ? "Save Service"
                          : "Create Service"}
                </Button>
            </div>
        </form>
    );
}
