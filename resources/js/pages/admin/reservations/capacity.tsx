import { router, useForm } from "@inertiajs/react";
import type { ReactNode } from "react";
import { formatManilaEvent } from "../../../lib/date-time";
import AdminLayout from "../../../layouts/admin-layout";

type Override = {
    id: number;
    event_date: string;
    maximum_confirmed_events: number;
    maximum_guests: number | null;
    updated_at: string;
    updated_by: { name: string } | null;
};

export default function ReservationCapacity({
    overrides,
}: {
    overrides: { data: Override[] };
}) {
    const form = useForm({
        event_date: "",
        maximum_confirmed_events: "3",
        maximum_guests: "",
    });
    return (
        <AdminLayout
            title="Date capacity overrides"
            description="Use an override when a specific event date needs a different confirmed-event or optional guest limit. It affects future confirmation decisions only; existing reservations retain their snapshot."
            section="reservations"
        >
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post("/admin/reservations/capacity", {
                        onSuccess: () => form.reset(),
                    });
                }}
                className="rounded-2xl border bg-white p-5"
            >
                <h2 className="font-semibold">Set an override</h2>
                <div className="mt-4 grid gap-4 sm:grid-cols-3">
                    <Field label="Event date" error={form.errors.event_date}>
                        <input
                            type="date"
                            value={form.data.event_date}
                            onChange={(event) =>
                                form.setData("event_date", event.target.value)
                            }
                            className="mt-1 w-full"
                        />
                    </Field>
                    <Field
                        label="Confirmed events allowed"
                        error={form.errors.maximum_confirmed_events}
                    >
                        <input
                            type="number"
                            min="1"
                            value={form.data.maximum_confirmed_events}
                            onChange={(event) =>
                                form.setData(
                                    "maximum_confirmed_events",
                                    event.target.value,
                                )
                            }
                            className="mt-1 w-full"
                        />
                    </Field>
                    <Field
                        label="Guest capacity (optional)"
                        error={form.errors.maximum_guests}
                    >
                        <input
                            type="number"
                            min="1"
                            value={form.data.maximum_guests}
                            onChange={(event) =>
                                form.setData(
                                    "maximum_guests",
                                    event.target.value,
                                )
                            }
                            className="mt-1 w-full"
                        />
                    </Field>
                </div>
                <button
                    disabled={form.processing}
                    className="mt-5 rounded-lg bg-emerald-800 px-4 py-2 text-sm font-semibold text-white"
                >
                    Save date override
                </button>
            </form>
            <section className="mt-6 overflow-hidden rounded-2xl border bg-white">
                <h2 className="border-b p-5 font-semibold">Configured dates</h2>
                <ul className="divide-y">
                    {overrides.data.map((override) => (
                        <li
                            key={override.id}
                            className="flex flex-wrap items-center justify-between gap-4 p-5 text-sm"
                        >
                            <div>
                                <strong>
                                    {formatManilaEvent(
                                        override.event_date,
                                        null,
                                    )}
                                </strong>
                                <p className="mt-1 text-slate-600">
                                    {override.maximum_confirmed_events}{" "}
                                    confirmed events ·{" "}
                                    {override.maximum_guests ??
                                        "No guest limit"}
                                    {override.maximum_guests ? " guests" : ""}
                                </p>
                            </div>
                            <button
                                onClick={() =>
                                    window.confirm(
                                        "Remove this date override? Future checks will use the Business Policies default.",
                                    ) &&
                                    router.delete(
                                        `/admin/reservations/capacity/${override.id}`,
                                    )
                                }
                                className="text-sm font-semibold text-rose-700 underline"
                            >
                                Remove override
                            </button>
                        </li>
                    ))}
                    {overrides.data.length === 0 && (
                        <li className="p-5 text-sm text-slate-600">
                            No date-specific capacity overrides are configured.
                        </li>
                    )}
                </ul>
            </section>
        </AdminLayout>
    );
}

function Field({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <label className="text-sm font-medium">
            {label}
            {children}
            {error && (
                <span role="alert" className="mt-1 block text-sm text-rose-700">
                    {error}
                </span>
            )}
        </label>
    );
}
