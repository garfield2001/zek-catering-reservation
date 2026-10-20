import { Link } from "@inertiajs/react";
import { useState } from "react";
import { csrfFetch, sessionFailureMessage } from "../../../lib/http";
import { Modal } from "../../../components/ui/modal";
import { Button } from "../../../components/ui/button";
import { useDialogForm } from "../../../components/ui/dialog-form-guard";
import type { MeasurementUnit } from "./measurement-unit-form";

function CreateUnit({
    onCreated,
}: {
    onCreated: (unit: MeasurementUnit) => void;
}) {
    const [name, setName] = useState("");
    const [abbreviation, setAbbreviation] = useState("");
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState("");
    useDialogForm(Boolean(name || abbreviation), busy);
    return (
        <form
            className="space-y-4"
            onSubmit={async (event) => {
                event.preventDefault();
                // React portal events still bubble to the unsaved package form.
                event.stopPropagation();
                if (busy) return;
                setBusy(true);
                setError("");
                try {
                    const response = await csrfFetch(
                        "/admin/catalog/measurement-units",
                        {
                            method: "POST",
                            credentials: "same-origin",
                            headers: {
                                "Content-Type": "application/json",
                                Accept: "application/json",
                            },
                            body: JSON.stringify({
                                name,
                                abbreviation,
                                active: true,
                            }),
                        },
                    );
                    if (response.status === 403) {
                        setError(
                            "You cannot create Measurement Units. Ask an Administrator for catalog access.",
                        );
                        return;
                    }
                    const sessionError = sessionFailureMessage(response);
                    if (sessionError) {
                        setError(sessionError);
                        return;
                    }
                    const result = await response.json();
                    if (!response.ok) {
                        setError(
                            Object.values(result.errors ?? {})
                                .flat()
                                .join(" ") ||
                                "The unit could not be saved. Try again.",
                        );
                        return;
                    }
                    onCreated(result.unit as MeasurementUnit);
                } catch {
                    setError(
                        "Could not confirm the save. Your package input is unchanged. Check the connection before retrying.",
                    );
                } finally {
                    setBusy(false);
                }
            }}
        >
            <p className="text-sm text-slate-600">
                Your unsaved package remains open. The new unit will be selected
                here.
            </p>
            <label className="block text-sm">
                Unit name
                <input
                    required
                    maxLength={100}
                    value={name}
                    onChange={(event) => setName(event.target.value)}
                />
            </label>
            <label className="block text-sm">
                Abbreviation
                <input
                    required
                    maxLength={20}
                    value={abbreviation}
                    onChange={(event) => setAbbreviation(event.target.value)}
                />
            </label>
            {error && (
                <p role="alert" className="text-sm text-red-700">
                    {error}
                </p>
            )}
            <Button disabled={busy}>
                {busy ? "Creating…" : "Create Measurement Unit"}
            </Button>
        </form>
    );
}

export function InlineMeasurementUnit({
    onCreated,
}: {
    onCreated: (unit: MeasurementUnit) => void;
}) {
    const [open, setOpen] = useState(false);
    return (
        <>
            <button
                type="button"
                className="text-sm text-emerald-800 underline"
                onClick={() => setOpen(true)}
            >
                Add Measurement Unit
            </button>
            <Link
                href="/admin/catalog/measurement-units"
                className="ml-3 text-sm text-emerald-800 underline"
            >
                Manage units
            </Link>
            <Modal
                open={open}
                onClose={() => setOpen(false)}
                title="Create Measurement Unit"
            >
                <CreateUnit
                    onCreated={(unit) => {
                        onCreated(unit);
                        setOpen(false);
                    }}
                />
            </Modal>
        </>
    );
}
