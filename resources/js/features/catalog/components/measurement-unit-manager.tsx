import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { ConfirmDialog } from "../../../components/ui/confirm-dialog";
import { Modal } from "../../../components/ui/modal";
import { Button } from "../../../components/ui/button";
import { csrfFetch, sessionFailureMessage } from "../../../lib/http";
import type { MeasurementUnit } from "./measurement-unit-form";

type ManagedUnit = MeasurementUnit & { usage_count: number };
type UnitForm = { name: string; abbreviation: string; active: boolean };

const emptyForm = (): UnitForm => ({
    name: "",
    abbreviation: "",
    active: true,
});

/** Reuses the catalog unit endpoints without navigating away from the current workspace. */
export function MeasurementUnitManager({
    open,
    onClose,
    onUnitsChanged,
}: {
    open: boolean;
    onClose: () => void;
    onUnitsChanged?: (units: MeasurementUnit[]) => void;
}) {
    const [units, setUnits] = useState<ManagedUnit[]>([]);
    const [canManage, setCanManage] = useState(false);
    const [search, setSearch] = useState("");
    const [editing, setEditing] = useState<ManagedUnit | "new" | null>(null);
    const [form, setForm] = useState<UnitForm>(emptyForm);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [notice, setNotice] = useState("");
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const [deleting, setDeleting] = useState<ManagedUnit | null>(null);
    const [deleteError, setDeleteError] = useState("");
    const onUnitsChangedRef = useRef(onUnitsChanged);

    useEffect(() => {
        onUnitsChangedRef.current = onUnitsChanged;
    }, [onUnitsChanged]);

    const refresh = useCallback(async () => {
        setLoading(true);
        try {
            const response = await fetch("/admin/catalog/measurement-units", {
                credentials: "same-origin",
                headers: { Accept: "application/json" },
            });
            const sessionError = sessionFailureMessage(response);
            if (sessionError) {
                setNotice(sessionError);
                return;
            }
            const result = await response.json();
            if (!response.ok) {
                setNotice("Measurement Units could not be loaded. Try again.");
                return;
            }
            const saved = result.units as ManagedUnit[];
            setUnits(saved);
            setCanManage(Boolean(result.canManage));
            onUnitsChangedRef.current?.(
                saved
                    .filter((unit) => unit.active)
                    .map((unit) => ({
                        id: unit.id,
                        name: unit.name,
                        abbreviation: unit.abbreviation,
                        active: unit.active,
                    })),
            );
        } catch {
            setNotice(
                "Measurement Units could not be loaded. Check the connection and try again.",
            );
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        if (!open) return;
        const frame = window.requestAnimationFrame(() => void refresh());
        return () => window.cancelAnimationFrame(frame);
    }, [open, refresh]);

    const visible = useMemo(() => {
        const term = search.trim().toLocaleLowerCase();
        return term
            ? units.filter(
                  (unit) =>
                      unit.name.toLocaleLowerCase().includes(term) ||
                      unit.abbreviation.toLocaleLowerCase().includes(term),
              )
            : units;
    }, [search, units]);

    const startEditing = (unit: ManagedUnit | "new") => {
        setNotice("");
        setErrors({});
        setEditing(unit);
        setForm(
            unit === "new"
                ? emptyForm()
                : {
                      name: unit.name,
                      abbreviation: unit.abbreviation,
                      active: unit.active,
                  },
        );
    };

    const hasUnsavedChanges =
        editing !== null &&
        (editing === "new"
            ? form.name !== "" || form.abbreviation !== ""
            : form.name !== editing.name ||
              form.abbreviation !== editing.abbreviation ||
              form.active !== editing.active);

    const save = async () => {
        if (!editing || saving) return;
        setSaving(true);
        setErrors({});
        setNotice("");
        try {
            const response = await csrfFetch(
                editing === "new"
                    ? "/admin/catalog/measurement-units"
                    : `/admin/catalog/measurement-units/${editing.id}`,
                {
                    method: editing === "new" ? "POST" : "PUT",
                    credentials: "same-origin",
                    headers: {
                        "Content-Type": "application/json",
                        Accept: "application/json",
                    },
                    body: JSON.stringify(form),
                },
            );
            const sessionError = sessionFailureMessage(response);
            if (sessionError) {
                setNotice(sessionError);
                return;
            }
            const result = await response.json();
            if (!response.ok) {
                const nextErrors = Object.fromEntries(
                    Object.entries(result.errors ?? {}).map(([key, value]) => [
                        key,
                        Array.isArray(value) ? value.join(" ") : String(value),
                    ]),
                );
                setErrors(nextErrors);
                setNotice(
                    Object.keys(nextErrors).length
                        ? "Review the highlighted information and try again."
                        : "The Measurement Unit could not be saved. Try again.",
                );
                return;
            }
            setEditing(null);
            setNotice(
                editing === "new"
                    ? "Measurement Unit created."
                    : "Measurement Unit saved.",
            );
            await refresh();
        } catch {
            setNotice(
                "Could not confirm the save. Your changes are still open; check the connection before retrying.",
            );
        } finally {
            setSaving(false);
        }
    };

    const remove = async () => {
        if (!deleting || saving) return;
        setSaving(true);
        setDeleteError("");
        try {
            const response = await csrfFetch(
                `/admin/catalog/measurement-units/${deleting.id}`,
                {
                    method: "DELETE",
                    credentials: "same-origin",
                    headers: { Accept: "application/json" },
                },
            );
            const sessionError = sessionFailureMessage(response);
            if (sessionError) {
                setDeleteError(sessionError);
                return;
            }
            if (!response.ok) {
                const result = await response.json();
                setDeleteError(
                    Object.values(result.errors ?? {})
                        .flat()
                        .join(" ") ||
                        "This Measurement Unit could not be deleted. Refresh and review its saved use before trying again.",
                );
                return;
            }
            setDeleting(null);
            setNotice("Measurement Unit deleted.");
            await refresh();
        } catch {
            setDeleteError(
                "Could not confirm deletion. Check the connection before retrying.",
            );
        } finally {
            setSaving(false);
        }
    };

    return (
        <>
            <Modal
                open={open}
                onClose={onClose}
                title="Manage Measurement Units"
                wide
                dirty={hasUnsavedChanges}
                busy={saving}
            >
                <div className="space-y-5">
                    <p className="text-sm text-slate-600">
                        Use these labels for portions, packaging, and sizes.
                        They do not convert automatically.
                    </p>
                    {notice && (
                        <p
                            role="status"
                            className="rounded-lg bg-slate-50 p-3 text-sm"
                        >
                            {notice}
                        </p>
                    )}
                    {editing ? (
                        <section
                            className="rounded-xl border bg-slate-50 p-4"
                            aria-label="Measurement Unit form"
                        >
                            <div className="mb-3 flex items-center justify-between gap-3">
                                <h3 className="font-semibold">
                                    {editing === "new"
                                        ? "Add Measurement Unit"
                                        : `Edit ${editing.name}`}
                                </h3>
                                <button
                                    type="button"
                                    className="text-sm text-emerald-800 underline"
                                    onClick={() => setEditing(null)}
                                    disabled={saving}
                                >
                                    Back to units
                                </button>
                            </div>
                            {editing !== "new" && editing.usage_count > 0 && (
                                <p className="mb-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
                                    This unit is used by {editing.usage_count}{" "}
                                    saved setting
                                    {editing.usage_count === 1 ? "" : "s"}. Keep
                                    its name and symbol; deactivate it instead
                                    if it should not be selected again.
                                </p>
                            )}
                            <div className="grid gap-3 sm:grid-cols-2">
                                <label className="text-sm">
                                    Unit name
                                    <input
                                        aria-invalid={Boolean(errors.name)}
                                        disabled={
                                            saving ||
                                            (editing !== "new" &&
                                                editing.usage_count > 0)
                                        }
                                        value={form.name}
                                        maxLength={100}
                                        onChange={(event) =>
                                            setForm({
                                                ...form,
                                                name: event.target.value,
                                            })
                                        }
                                    />
                                </label>
                                <label className="text-sm">
                                    Symbol
                                    <input
                                        aria-invalid={Boolean(
                                            errors.abbreviation,
                                        )}
                                        disabled={
                                            saving ||
                                            (editing !== "new" &&
                                                editing.usage_count > 0)
                                        }
                                        value={form.abbreviation}
                                        maxLength={20}
                                        onChange={(event) =>
                                            setForm({
                                                ...form,
                                                abbreviation:
                                                    event.target.value,
                                            })
                                        }
                                    />
                                </label>
                            </div>
                            {(errors.name || errors.abbreviation) && (
                                <p
                                    role="alert"
                                    className="mt-2 text-sm text-red-700"
                                >
                                    {errors.name ?? errors.abbreviation}
                                </p>
                            )}
                            <label className="mt-3 flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={form.active}
                                    disabled={saving}
                                    onChange={(event) =>
                                        setForm({
                                            ...form,
                                            active: event.target.checked,
                                        })
                                    }
                                />{" "}
                                Active Measurement Unit
                            </label>
                            {!form.active && (
                                <p className="mt-2 text-sm text-amber-900">
                                    Inactive units cannot be selected for new
                                    settings. Existing package and publication
                                    facts remain available.
                                </p>
                            )}
                            <div className="mt-4">
                                <Button
                                    type="button"
                                    disabled={saving}
                                    onClick={() => void save()}
                                >
                                    {saving
                                        ? "Saving…"
                                        : editing === "new"
                                          ? "Create Measurement Unit"
                                          : "Save Measurement Unit"}
                                </Button>
                            </div>
                        </section>
                    ) : (
                        <>
                            <div className="flex flex-wrap items-end justify-between gap-3">
                                <label className="text-sm">
                                    Search units
                                    <input
                                        value={search}
                                        onChange={(event) =>
                                            setSearch(event.target.value)
                                        }
                                        placeholder="Name or symbol"
                                    />
                                </label>
                                {canManage && (
                                    <Button
                                        type="button"
                                        onClick={() => startEditing("new")}
                                    >
                                        Add Measurement Unit
                                    </Button>
                                )}
                            </div>
                            <div className="max-h-[52dvh] overflow-auto rounded-xl border">
                                <table
                                    className="w-full text-left text-sm"
                                    aria-label="Saved Measurement Units"
                                >
                                    <thead className="sticky top-0 bg-slate-50">
                                        <tr>
                                            <th className="p-3">Name</th>
                                            <th className="p-3">Symbol</th>
                                            <th className="p-3">Status</th>
                                            <th className="p-3">Use</th>
                                            {canManage && (
                                                <th className="p-3">Actions</th>
                                            )}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {visible.map((unit) => (
                                            <tr
                                                key={unit.id}
                                                className="border-t"
                                            >
                                                <td className="p-3">
                                                    {unit.name}
                                                </td>
                                                <td className="p-3">
                                                    {unit.abbreviation}
                                                </td>
                                                <td className="p-3">
                                                    {unit.active
                                                        ? "Active"
                                                        : "Inactive"}
                                                </td>
                                                <td className="p-3">
                                                    {unit.usage_count
                                                        ? `${unit.usage_count} saved setting${unit.usage_count === 1 ? "" : "s"}`
                                                        : "Not used"}
                                                </td>
                                                {canManage && (
                                                    <td className="space-x-3 whitespace-nowrap p-3">
                                                        <button
                                                            type="button"
                                                            className="text-emerald-800 underline"
                                                            onClick={() =>
                                                                startEditing(
                                                                    unit,
                                                                )
                                                            }
                                                        >
                                                            {unit.active
                                                                ? "Edit or deactivate"
                                                                : "Edit or reactivate"}
                                                        </button>
                                                        {unit.usage_count ? (
                                                            <span className="text-xs text-slate-500">
                                                                Cannot delete
                                                                while used.
                                                            </span>
                                                        ) : (
                                                            <button
                                                                type="button"
                                                                className="text-rose-700 underline"
                                                                onClick={() => {
                                                                    setDeleteError(
                                                                        "",
                                                                    );
                                                                    setDeleting(
                                                                        unit,
                                                                    );
                                                                }}
                                                            >
                                                                Delete
                                                            </button>
                                                        )}
                                                    </td>
                                                )}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                                {!loading && !visible.length && (
                                    <p className="p-4 text-sm text-slate-600">
                                        {units.length
                                            ? "No units match this search."
                                            : "No Measurement Units yet. Add the units your business uses."}
                                    </p>
                                )}
                                {loading && (
                                    <p className="p-4 text-sm text-slate-600">
                                        Loading Measurement Units…
                                    </p>
                                )}
                            </div>
                            {!loading && !canManage && (
                                <p className="rounded-lg bg-amber-50 p-3 text-sm">
                                    You can view Measurement Units but cannot
                                    manage them. Ask an Administrator for
                                    catalog management access.
                                </p>
                            )}
                        </>
                    )}
                </div>
            </Modal>
            <ConfirmDialog
                open={deleting !== null}
                onClose={() => {
                    if (!saving) setDeleting(null);
                }}
                title="Delete Measurement Unit?"
                description={
                    deleteError ||
                    `Delete ${deleting?.name ?? "this unit"}? This is permanent. Only unused units can be deleted.`
                }
                confirmLabel="Delete Unit"
                destructive
                busy={saving}
                onConfirm={() => void remove()}
            />
        </>
    );
}
