import { useDialogForm } from "../../../components/ui/dialog-form-guard";
import { useForm } from "@inertiajs/react";
import { useJsonSave } from "../use-json-save";
import { ModalClose } from "../../../components/ui/modal";
import { Button } from "../../../components/ui/button";
import { Field } from "../../../components/ui/field";

export const serviceBehaviors = [
    {
        value: "package",
        label: "Sold by Package",
        detail: "Sell sets with included foods, serving sizes and customer choices.",
    },
    {
        value: "packed_meal",
        label: "Packed Meals",
        detail: "Price each pack, with the same meal for everyone or split meal groups.",
    },
    {
        value: "fixed_bundle",
        label: "Fixed Bundle",
        detail: "Sell a set of fixed inclusions. No substitutions or food surcharges.",
    },
    {
        value: "direct_sale",
        label: "Sold Individually",
        detail: "Independently priced items with their own Drafts and Published Versions.",
    },
] as const;

export function CollectionCreateForm({
    onCreated,
}: {
    hasPrimary?: boolean;
    onCreated: (service: { id: number; name: string }) => void;
}) {
    const form = useForm({
        kind: "addon",
        behavior_type: "package",
        name: "",
        description: "",
        active: true,
    });
    const request = useJsonSave<{ service: { id: number; name: string } }>();
    useDialogForm(form.isDirty, request.saving);
    return (
        <form
            className="space-y-5"
            onSubmit={async (e) => {
                e.preventDefault();
                if (request.saving) return;
                form.clearErrors();
                const result = await request.save(
                    "/admin/add-on-services",
                    "POST",
                    form.data,
                    form.setError,
                );
                if (result) onCreated(result.service);
            }}
        >
            {request.error && <p role="alert">{request.error}</p>}
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
                    onChange={(e) => form.setData("name", e.target.value)}
                />
            </Field>
            <fieldset>
                <legend className="mb-3 text-sm font-medium">
                    How will you sell this service?
                </legend>
                <div className="mb-4 grid gap-3 sm:grid-cols-2">
                    <label className="flex items-center gap-3 rounded-xl border p-4">
                        <input
                            type="radio"
                            name="selling-method"
                            checked={form.data.behavior_type !== "direct_sale"}
                            onChange={() =>
                                form.setData("behavior_type", "package")
                            }
                        />
                        <span>
                            <strong className="block text-sm">
                                Sold by Package
                            </strong>
                            <span className="text-xs text-slate-500">
                                Configurable sets, packed meals or fixed bundles
                            </span>
                        </span>
                    </label>
                    <label className="flex items-center gap-3 rounded-xl border p-4">
                        <input
                            type="radio"
                            name="selling-method"
                            checked={form.data.behavior_type === "direct_sale"}
                            onChange={() =>
                                form.setData("behavior_type", "direct_sale")
                            }
                        />
                        <span>
                            <strong className="block text-sm">
                                Sold Individually
                            </strong>
                            <span className="text-xs text-slate-500">
                                Independently priced foods and sizes
                            </span>
                        </span>
                    </label>
                </div>
            </fieldset>
            <Field
                id="service-description"
                label="Description (optional)"
                error={form.errors.description}
            >
                <textarea
                    id="service-description"
                    value={form.data.description}
                    onChange={(e) =>
                        form.setData("description", e.target.value)
                    }
                />
            </Field>
            {Object.entries(form.errors)
                .filter(([key]) => key !== "name" && key !== "description")
                .map(([key, error]) => (
                    <p role="alert" key={key} className="text-sm text-red-700">
                        {error}
                    </p>
                ))}
            <div className="flex justify-end gap-3">
                <ModalClose type="button">Cancel</ModalClose>
                <Button disabled={request.saving}>Create Service</Button>
            </div>
        </form>
    );
}
