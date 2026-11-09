import { Link, router, useForm } from "@inertiajs/react";
import AdminLayout from "../../../layouts/admin-layout";
import { formatManilaEvent } from "../../../lib/date-time";

type Booking = {
    id: number;
    status: string;
    customer_name: string;
    customer_mobile: string | null;
    customer_email: string | null;
    event_date: string;
    follow_up_on: string | null;
};
export default function PencilBookingsIndex({
    bookings,
    search,
    canManage,
}: {
    bookings: { data: Booking[] };
    search: string;
    canManage: boolean;
}) {
    const form = useForm({
        idempotency_key: "",
        customer_name: "",
        customer_mobile: "",
        customer_email: "",
        event_date: "",
        event_start_time: "",
        venue_information: "",
        guest_count: "",
        notes: "",
        follow_up_on: "",
    });
    return (
        <AdminLayout
            title="Pencil Bookings"
            description="Internal leads only. Pencil bookings do not hold capacity, create a quotation, or confirm an event."
            section="requests"
        >
            <div className="flex gap-3">
                <input
                    defaultValue={search}
                    placeholder="Search customer or contact"
                    onChange={(e) =>
                        router.get(
                            "/admin/pencil-bookings",
                            { search: e.target.value },
                            { preserveState: true, replace: true },
                        )
                    }
                />
            </div>
            {canManage && (
                <form
                    className="mt-5 grid gap-3 rounded-2xl border bg-white p-5 sm:grid-cols-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.transform((d) => ({
                            ...d,
                            idempotency_key: crypto.randomUUID(),
                        }));
                        form.post("/admin/pencil-bookings");
                    }}
                >
                    <label>
                        Customer name
                        <input
                            value={form.data.customer_name}
                            onChange={(e) =>
                                form.setData("customer_name", e.target.value)
                            }
                        />
                    </label>
                    <label>
                        Mobile number
                        <input
                            value={form.data.customer_mobile}
                            onChange={(e) =>
                                form.setData("customer_mobile", e.target.value)
                            }
                        />
                    </label>
                    <label>
                        Email
                        <input
                            value={form.data.customer_email}
                            onChange={(e) =>
                                form.setData("customer_email", e.target.value)
                            }
                        />
                    </label>
                    <label>
                        Tentative event date
                        <input
                            type="date"
                            value={form.data.event_date}
                            onChange={(e) =>
                                form.setData("event_date", e.target.value)
                            }
                        />
                    </label>
                    <label>
                        Follow up on
                        <input
                            type="date"
                            value={form.data.follow_up_on}
                            onChange={(e) =>
                                form.setData("follow_up_on", e.target.value)
                            }
                        />
                    </label>
                    <button
                        disabled={form.processing}
                        className="w-fit rounded-lg bg-emerald-800 px-4 py-2 text-white"
                    >
                        Save pencil booking
                    </button>
                </form>
            )}
            <ul className="mt-6 divide-y rounded-2xl border bg-white">
                {bookings.data.map((b) => (
                    <li className="p-4" key={b.id}>
                        <Link
                            className="font-semibold text-emerald-800 underline"
                            href={`/admin/pencil-bookings/${b.id}`}
                        >
                            {b.customer_name}
                        </Link>
                        <p className="text-sm text-slate-600">
                            {formatManilaEvent(b.event_date)} ·{" "}
                            {b.status.replaceAll("_", " ")}
                            {b.follow_up_on
                                ? ` · follow up ${b.follow_up_on}`
                                : ""}
                        </p>
                    </li>
                ))}
            </ul>
        </AdminLayout>
    );
}
