import { useForm } from "@inertiajs/react";
import { Pencil } from "lucide-react";
import { useState } from "react";
import { Button } from "../../../components/ui/button";
import { Field } from "../../../components/ui/field";
import { Modal, ModalClose } from "../../../components/ui/modal";
import type { Collection } from "../types";
import { DeactivationImpact } from "./deactivation-impact";
import {
    ServicePackageFields,
    initialServicePackage,
} from "./service-package-fields";

export function CollectionPresentationForm({
    family,
    canManage,
}: {
    family: Collection;
    canManage: boolean;
}) {
    const [open, setOpen] = useState(false);
    const [configureLegacy, setConfigureLegacy] = useState(false);
    const form = useForm({
        name: family.name,
        description: family.description ?? "",
        active: family.active,
        package_configuration: family.package_format
            ? {
                  format: family.package_format,
                  quantity: family.default_presentation_quantity ?? null,
                  measurement_unit_id:
                      family.default_presentation_unit_id ?? null,
              }
            : initialServicePackage(),
    });
    if (!canManage) return null;
    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                className="inline-flex items-center gap-1 text-sm font-semibold text-emerald-800 hover:underline"
            >
                <Pencil size={15} aria-hidden="true" /> Edit
            </button>
            <Modal
                open={open}
                dirty={form.isDirty || configureLegacy}
                busy={form.processing}
                onClose={() => {
                    form.reset();
                    setConfigureLegacy(false);
                    setOpen(false);
                }}
                title={`Edit ${family.name}`}
            >
                <form
                    className="space-y-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.transform((data) =>
                            family.is_addon &&
                            family.behavior_type !== "direct_sale" &&
                            (family.package_format || configureLegacy)
                                ? data
                                : { ...data, package_configuration: undefined },
                        );
                        form.put(`/admin/catalog/families/${family.id}`, {
                            preserveScroll: true,
                            onSuccess: () => {
                                form.setDefaults(form.data);
                                setConfigureLegacy(false);
                                setOpen(false);
                            },
                        });
                    }}
                >
                    <p className="text-sm text-slate-600">
                        Configuration type:{" "}
                        {family.behavior_type === "main_package"
                            ? "Catering Packages"
                            : family.behavior_type === "package"
                              ? "Configurable package"
                              : family.behavior_type === "packed_meal"
                                ? "Packed Meals"
                                : family.behavior_type === "fixed_bundle"
                                  ? "Fixed Bundle"
                                  : "Direct Sale"}
                        . Renaming does not change pricing, eligibility or
                        existing links.
                    </p>
                    {family.is_addon ? (
                        <Field
                            id={`family-name-${family.id}`}
                            label="Customer-facing name"
                            error={form.errors.name}
                        >
                            <input
                                id={`family-name-${family.id}`}
                                required
                                maxLength={255}
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData("name", event.target.value)
                                }
                            />
                        </Field>
                    ) : (
                        <p className="text-sm">
                            Catering Packages is system-managed. Its name cannot
                            be edited.
                        </p>
                    )}
                    <Field
                        id={`family-description-${family.id}`}
                        label="Description"
                        error={form.errors.description}
                    >
                        <textarea
                            id={`family-description-${family.id}`}
                            maxLength={5000}
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData("description", event.target.value)
                            }
                        />
                    </Field>
                    {family.is_addon &&
                        family.behavior_type !== "direct_sale" &&
                        !family.package_format && (
                            <label className="flex items-start gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={configureLegacy}
                                    onChange={(event) =>
                                        setConfigureLegacy(event.target.checked)
                                    }
                                />
                                Configure format and containers for new
                                packages. Existing package history will not
                                change.
                            </label>
                        )}
                    {family.is_addon &&
                        family.behavior_type !== "direct_sale" &&
                        (family.package_format || configureLegacy) && (
                            <ServicePackageFields
                                value={form.data.package_configuration}
                                onChange={(value) =>
                                    form.setData("package_configuration", value)
                                }
                                locked={Boolean(
                                    family.package_format &&
                                    family.products_count,
                                )}
                            />
                        )}
                    {Object.entries(form.errors)
                        .filter(([key]) =>
                            key.startsWith("package_configuration"),
                        )
                        .map(([key, error]) => (
                            <p
                                role="alert"
                                className="text-sm text-red-700"
                                key={key}
                            >
                                {error}
                            </p>
                        ))}
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.active}
                            onChange={(event) =>
                                form.setData("active", event.target.checked)
                            }
                        />{" "}
                        Enabled for new configuration and selection
                    </label>
                    {!form.data.active && (
                        <DeactivationImpact kind="service" id={family.id} />
                    )}
                    <div className="flex justify-end gap-3 border-t border-slate-100 pt-4">
                        <ModalClose
                            type="button"
                            className="rounded-lg px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100"
                        >
                            Cancel
                        </ModalClose>
                        <Button disabled={form.processing}>Save changes</Button>
                    </div>
                </form>
            </Modal>
        </>
    );
}
