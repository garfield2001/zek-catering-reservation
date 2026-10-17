import { Link } from "@inertiajs/react";
import { useState } from "react";
import { Modal } from "../../../components/ui/modal";
import AdminLayout from "../../../layouts/admin-layout";
import {
    MeasurementUnitForm,
    type MeasurementUnit,
} from "../../../features/catalog/components/measurement-unit-form";

export default function MeasurementUnits({
    units,
    canManage,
}: {
    units: MeasurementUnit[];
    canManage: boolean;
}) {
    const [editing, setEditing] = useState<MeasurementUnit | null>(null);
    const [open, setOpen] = useState(false);
    return (
        <AdminLayout
            title="Measurement Units"
            description="Catalog Settings · Reusable labels for commercial size choices. No automatic unit conversions."
            section="catalog"
        >
            <Link
                href="/admin/food-catalog"
                className="mb-5 inline-block text-sm text-emerald-800 underline"
            >
                Back to Food Catalog
            </Link>
            <div className="space-y-6">
                <section
                    className="overflow-x-auto rounded-xl border bg-white p-5"
                    aria-label="Saved Measurement Units"
                >
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr>
                                <th className="p-3">Name</th>
                                <th className="p-3">Abbreviation</th>
                                <th className="p-3">Status</th>
                                {canManage && <th className="p-3">Action</th>}
                            </tr>
                        </thead>
                        <tbody>
                            {units.map((unit) => (
                                <tr key={unit.id} className="border-t">
                                    <td className="p-3">{unit.name}</td>
                                    <td className="p-3">{unit.abbreviation}</td>
                                    <td className="p-3">
                                        {unit.active ? "Active" : "Inactive"}
                                    </td>
                                    {canManage && (
                                        <td className="p-3">
                                            <button
                                                className="text-emerald-800 underline"
                                                onClick={() => {
                                                    setEditing(unit);
                                                    setOpen(true);
                                                }}
                                            >
                                                Edit {unit.name}
                                            </button>
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {!units.length && (
                        <p className="p-3 text-slate-600">
                            No Measurement Units yet. Create the units your
                            business uses; none are mandatory.
                        </p>
                    )}
                    <p className="mt-4 text-xs text-slate-500">
                        Deactivate units instead of deleting their historical
                        references.
                    </p>
                </section>
                {canManage ? (
                    <>
                        <button
                            className="rounded-lg bg-emerald-800 px-4 py-2 text-white"
                            onClick={() => {
                                setEditing(null);
                                setOpen(true);
                            }}
                        >
                            Add Measurement Unit
                        </button>
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
                    </>
                ) : (
                    <p className="rounded-xl bg-amber-50 p-4 text-sm">
                        You have viewing access. Ask an Administrator for
                        catalog editing permission to manage units.
                    </p>
                )}
            </div>
        </AdminLayout>
    );
}
