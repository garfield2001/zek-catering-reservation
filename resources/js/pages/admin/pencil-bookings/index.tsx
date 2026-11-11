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
            <label className="block max-w-md text-sm font-medium text-slate-800">
                Search pencil bookings
                <input
                    className="mt-1 w-full"
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
            </label>
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
                    <div className="sm:col-span-2">
                        <h2 className="font-semibold">Create pencil booking</h2>
                        <p className="mt-1 text-sm text-slate-600">
                            Record a non-binding prospect and the next
                            follow-up. This does not reserve capacity or create
                            a quotation.
                        </p>
                    </div>
                    <label className="text-sm font-medium">
                        Customer name
                        <input
                            required
                            value={form.data.customer_name}
                            onChange={(event) => {
                                form.setData(
                                    "customer_name",
                                    event.target.value,
                                );
                                form.clearErrors("customer_name");
                            }}
                            aria-invalid={Boolean(form.errors.customer_name)}
                            className={`mt-1 w-full ${form.errors.customer_name ? "border-rose-600" : ""}`}
                        />
                        <FormError error={form.errors.customer_name} />
                    </label>
                    <label className="text-sm font-medium">
                        Mobile number
                        <input
                            value={form.data.customer_mobile}
                            onChange={(event) => {
                                form.setData(
                                    "customer_mobile",
                                    event.target.value,
                                );
                                form.clearErrors("customer_mobile");
                            }}
                            aria-invalid={Boolean(form.errors.customer_mobile)}
                            aria-describedby="pencil-booking-contact-hint"
                            className={`mt-1 w-full ${form.errors.customer_mobile ? "border-rose-600" : ""}`}
                        />
                        <FormError error={form.errors.customer_mobile} />
                    </label>
                    <label className="text-sm font-medium">
                        Email address
                        <input
                            type="email"
                            value={form.data.customer_email}
                            onChange={(event) => {
                                form.setData(
                                    "customer_email",
                                    event.target.value,
                                );
                                form.clearErrors("customer_email");
                            }}
                            aria-invalid={Boolean(form.errors.customer_email)}
                            aria-describedby="pencil-booking-contact-hint"
                            className={`mt-1 w-full ${form.errors.customer_email ? "border-rose-600" : ""}`}
                        />
                        <FormError error={form.errors.customer_email} />
                    </label>
                    <p
                        id="pencil-booking-contact-hint"
                        className="sm:col-span-2 -mt-1 text-xs text-slate-500"
                    >
                        Provide at least one contact method.
                    </p>
                    <label className="text-sm font-medium">
                        Tentative event date
                        <input
                            required
                            type="date"
                            value={form.data.event_date}
                            onChange={(event) => {
                                form.setData("event_date", event.target.value);
                                form.clearErrors("event_date");
                            }}
                            aria-invalid={Boolean(form.errors.event_date)}
                            className={`mt-1 w-full ${form.errors.event_date ? "border-rose-600" : ""}`}
                        />
                        <FormError error={form.errors.event_date} />
                    </label>
                    <label className="text-sm font-medium">
                        Event start time{" "}
                        <span className="font-normal">(optional)</span>
                        <input
                            type="time"
                            value={form.data.event_start_time}
                            onChange={(event) => {
                                form.setData(
                                    "event_start_time",
                                    event.target.value,
                                );
                                form.clearErrors("event_start_time");
                            }}
                            aria-invalid={Boolean(form.errors.event_start_time)}
                            className={`mt-1 w-full ${form.errors.event_start_time ? "border-rose-600" : ""}`}
                        />
                        <FormError error={form.errors.event_start_time} />
                    </label>
                    <label className="text-sm font-medium">
                        Venue <span className="font-normal">(optional)</span>
                        <input
                            value={form.data.venue_information}
                            onChange={(event) => {
                                form.setData(
                                    "venue_information",
                                    event.target.value,
                                );
                                form.clearErrors("venue_information");
                            }}
                            aria-invalid={Boolean(
                                form.errors.venue_information,
                            )}
                            className={`mt-1 w-full ${form.errors.venue_information ? "border-rose-600" : ""}`}
                        />
                        <FormError error={form.errors.venue_information} />
                    </label>
                    <label className="text-sm font-medium">
                        Estimated guests{" "}
                        <span className="font-normal">(optional)</span>
                        <input
                            type="number"
                            min="1"
                            value={form.data.guest_count}
                            onChange={(event) => {
                                form.setData("guest_count", event.target.value);
                                form.clearErrors("guest_count");
                            }}
                            aria-invalid={Boolean(form.errors.guest_count)}
                            className={`mt-1 w-full ${form.errors.guest_count ? "border-rose-600" : ""}`}
                        />
                        <FormError error={form.errors.guest_count} />
                    </label>
                    <label className="text-sm font-medium">
                        Follow up on
                        <input
                            type="date"
                            value={form.data.follow_up_on}
                            onChange={(event) => {
                                form.setData(
                                    "follow_up_on",
                                    event.target.value,
                                );
                                form.clearErrors("follow_up_on");
                            }}
                            aria-invalid={Boolean(form.errors.follow_up_on)}
                            className={`mt-1 w-full ${form.errors.follow_up_on ? "border-rose-600" : ""}`}
                        />
                        <FormError error={form.errors.follow_up_on} />
                    </label>
                    <label className="sm:col-span-2 text-sm font-medium">
                        Notes <span className="font-normal">(optional)</span>
                        <textarea
                            rows={3}
                            value={form.data.notes}
                            onChange={(event) => {
                                form.setData("notes", event.target.value);
                                form.clearErrors("notes");
                            }}
                            aria-invalid={Boolean(form.errors.notes)}
                            className={`mt-1 w-full ${form.errors.notes ? "border-rose-600" : ""}`}
                        />
                        <FormError error={form.errors.notes} />
                    </label>
                    <button
                        disabled={form.processing}
                        className="w-fit rounded-lg bg-emerald-800 px-4 py-2 text-white"
                    >
                        {form.processing ? "Saving…" : "Save pencil booking"}
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

function FormError({ error }: { error?: string }) {
    return error ? (
        <span
            role="alert"
            className="mt-1 block text-sm font-normal text-rose-700"
        >
            {error}
        </span>
    ) : null;
}
