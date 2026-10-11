import { useForm } from "@inertiajs/react";
import { Pencil } from "lucide-react";
import { useState } from "react";
import { Button } from "../../../components/ui/button";
import { Field } from "../../../components/ui/field";
import { Modal } from "../../../components/ui/modal";
import type { Collection } from "../types";

export function CollectionPresentationForm({
    family,
    canManage,
}: {
    family: Collection;
    canManage: boolean;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        name: family.name,
        description: family.description ?? "",
        active: family.active,
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
                onClose={() => setOpen(false)}
                title={`Edit ${family.name}`}
            >
                <form
                    className="space-y-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put(`/admin/catalog/families/${family.id}`, {
                            onSuccess: () => setOpen(false),
                        });
                    }}
                >
                    <p className="text-sm text-slate-600">
                        Configuration type:{" "}
                        {family.behavior_type.replaceAll("_", " ")}. Renaming
                        does not change pricing, eligibility or existing links.
                    </p>
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
                    <div className="flex justify-end gap-3 border-t border-slate-100 pt-4">
                        <button
                            type="button"
                            onClick={() => setOpen(false)}
                            className="rounded-lg px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100"
                        >
                            Cancel
                        </button>
                        <Button disabled={form.processing}>Save changes</Button>
                    </div>
                </form>
            </Modal>
        </>
    );
}
