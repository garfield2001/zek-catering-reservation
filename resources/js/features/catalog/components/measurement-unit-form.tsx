import { useDialogForm } from "../../../components/ui/dialog-form-guard";
import { DeactivationImpact } from "./deactivation-impact";
import { useForm } from "@inertiajs/react";
import { useId } from "react";
import { Button } from "../../../components/ui/button";
import { Field } from "../../../components/ui/field";
import { UnsavedChanges } from "../../../components/ui/unsaved-changes";

export type MeasurementUnit = {
    id: number;
    name: string;
    abbreviation: string;
    active: boolean;
};

export function MeasurementUnitForm({
    unit,
    onSaved,
}: {
    unit: MeasurementUnit | null;
    onSaved: () => void;
}) {
    const prefix = useId();
    const form = useForm({
        name: unit?.name ?? "",
        abbreviation: unit?.abbreviation ?? "",
        active: unit?.active ?? true,
    });
    useDialogForm(form.isDirty, form.processing);
    return (
        <form
            className="space-y-4"
            onSubmit={(event) => {
                event.preventDefault();
                const options = {
                    onSuccess: () => {
                        form.setDefaults(form.data);
                        onSaved();
                    },
                    preserveScroll: true,
                };
                if (unit)
                    form.put(
                        `/admin/catalog/measurement-units/${unit.id}`,
                        options,
                    );
                else form.post("/admin/catalog/measurement-units", options);
            }}
        >
            <UnsavedChanges dirty={form.isDirty && !form.processing} />
            <Field
                id={`${prefix}-name`}
                label="Unit name"
                error={form.errors.name}
            >
                <input
                    id={`${prefix}-name`}
                    required
                    maxLength={100}
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData("name", event.target.value)
                    }
                />
            </Field>
            <Field
                id={`${prefix}-abbreviation`}
                label="Abbreviation"
                error={form.errors.abbreviation}
            >
                <input
                    id={`${prefix}-abbreviation`}
                    required
                    maxLength={20}
                    value={form.data.abbreviation}
                    onChange={(event) =>
                        form.setData("abbreviation", event.target.value)
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
                />
                Active Measurement Unit
            </label>
            {!form.data.active && (
                <p className="text-sm text-amber-800">
                    Inactive units cannot be chosen for new size configurations.
                    Historical published measurements are retained.
                </p>
            )}
            {unit && !form.data.active && (
                <DeactivationImpact kind="unit" id={unit.id} />
            )}
            <Button disabled={form.processing}>
                {form.processing
                    ? "Saving…"
                    : unit
                      ? "Save Measurement Unit"
                      : "Create Measurement Unit"}
            </Button>
        </form>
    );
}
