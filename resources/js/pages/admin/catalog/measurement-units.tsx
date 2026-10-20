import { Link, router } from "@inertiajs/react";
import { useMemo, useState } from "react";
import { ConfirmDialog } from "../../../components/ui/confirm-dialog";
import { Modal } from "../../../components/ui/modal";
import AdminLayout from "../../../layouts/admin-layout";
import {
    MeasurementUnitForm,
    type MeasurementUnit,
} from "../../../features/catalog/components/measurement-unit-form";

type ManagedUnit = MeasurementUnit & { usage_count: number };

export default function MeasurementUnits({
    units,
    canManage,
}: {
    units: ManagedUnit[];
    canManage: boolean;
}) {
    const [editing, setEditing] = useState<ManagedUnit | null>(null);
    const [deleting, setDeleting] = useState<ManagedUnit | null>(null);
    const [deleteError, setDeleteError] = useState<string | null>(null);
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState("");
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
    return (
        <AdminLayout
            title="Measurement Units"
            description="Manage the units used for food portions, packaging and size choices. Units do not convert automatically."
            section="catalog"
        >
            <Link
                href="/admin/food-catalog"
                className="mb-5 inline-block text-sm text-emerald-800 underline"
            >
                Back to Food Catalog
            </Link>
            <div className="space-y-5">
                <section className="rounded-xl border bg-white p-5">
                    <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                        <label className="block text-sm">
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
                            <button
                                className="rounded-lg bg-emerald-800 px-4 py-2 text-white"
                                onClick={() => {
                                    setEditing(null);
                                    setOpen(true);
                                }}
                            >
                                Add Measurement Unit
                            </button>
                        )}
                    </div>
                    <p className="mb-4 text-sm text-slate-600">
                        Active units can be selected for new settings. Used
                        units keep existing package and published-history facts;
                        deactivate them instead of renaming or deleting them.
                    </p>
                    <div className="overflow-x-auto">
                        <table
                            className="w-full text-left text-sm"
                            aria-label="Saved Measurement Units"
                        >
                            <thead>
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
                                    <tr key={unit.id} className="border-t">
                                        <td className="p-3">{unit.name}</td>
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
                                                    className="text-emerald-800 underline"
                                                    onClick={() => {
                                                        setEditing(unit);
                                                        setOpen(true);
                                                    }}
                                                >
                                                    {unit.active
                                                        ? "Edit or deactivate"
                                                        : "Edit or reactivate"}
                                                </button>
                                                {unit.usage_count ? (
                                                    <span className="text-xs text-slate-500">
                                                        Cannot delete while
                                                        used; deactivate
                                                        instead.
                                                    </span>
                                                ) : (
                                                    <button
                                                        className="text-rose-700 underline"
                                                        onClick={() => {
                                                            setDeleteError(
                                                                null,
                                                            );
                                                            setDeleting(unit);
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
                    </div>
                    {!visible.length && (
                        <p className="p-3 text-slate-600">
                            {units.length
                                ? "No units match this search."
                                : "No Measurement Units yet. Add the units your business uses."}
                        </p>
                    )}
                </section>
                {!canManage && (
                    <p className="rounded-xl bg-amber-50 p-4 text-sm">
                        You can view units but cannot manage them. Ask an
                        Administrator for catalog management access.
                    </p>
                )}
            </div>
            {canManage && (
                <Modal
                    open={open}
                    onClose={() => setOpen(false)}
                    title={
                        editing
                            ? `Edit ${editing.name}`
                            : "New Measurement Unit"
                    }
                >
                    <MeasurementUnitForm
                        key={editing?.id ?? "new"}
                        unit={editing}
                        onSaved={() => setOpen(false)}
                    />
                </Modal>
            )}
            <ConfirmDialog
                open={deleting !== null}
                onClose={() => setDeleting(null)}
                title="Delete Measurement Unit?"
                description={
                    deleteError ??
                    `Delete ${deleting?.name ?? "this unit"}? This is permanent. Only unused units can be deleted.`
                }
                confirmLabel="Delete Unit"
                onConfirm={() => {
                    if (!deleting) return;
                    router.delete(
                        `/admin/catalog/measurement-units/${deleting.id}`,
                        {
                            preserveScroll: true,
                            onSuccess: () => setDeleting(null),
                            onError: (errors) =>
                                setDeleteError(
                                    errors.unit ??
                                        "This Measurement Unit could not be deleted. Refresh and review its saved use before trying again.",
                                ),
                        },
                    );
                }}
            />
        </AdminLayout>
    );
}
