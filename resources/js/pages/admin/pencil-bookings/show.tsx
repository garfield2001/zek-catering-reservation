import { Link, useForm } from "@inertiajs/react";
import { InternalRequestForm } from "../../../features/reservation-requests/components/internal-request-form";
import type {
    PublicListing,
    PublicPackage,
} from "../../../features/storefront/types";
import AdminLayout from "../../../layouts/admin-layout";
import { formatManilaTimestamp } from "../../../lib/date-time";

type Catalog = {
    main_packages: PublicPackage[];
    add_on_services: Array<{
        id: number;
        name: string;
        description: string | null;
        packages: PublicPackage[];
        listings: PublicListing[];
    }>;
};
type Booking = {
    id: number;
    status: string;
    customer_name: string;
    customer_mobile: string | null;
    customer_email: string | null;
    event_date: string;
    event_start_time: string | null;
    venue_information: string | null;
    guest_count: number | null;
    notes: string | null;
    follow_up_on: string | null;
    updated_at: string;
    converted_reservation_request: {
        id: number;
        reference: string;
        quotation: { id: number } | null;
    } | null;
    events: Array<{
        id: number;
        event_type: string;
        details: { reason?: string } | null;
        created_at: string;
        actor: { name: string } | null;
    }>;
};

export default function PencilBookingShow({
    booking,
    canManage,
    canCreateRequest,
    canConvert,
    catalog,
    idempotencyKey,
    minimumAdvanceBookingDays,
    canAuthorizeEmergencyBooking,
}: {
    booking: Booking;
    canManage: boolean;
    canCreateRequest: boolean;
    canConvert: boolean;
    catalog: Catalog | null;
    idempotencyKey: string | null;
    minimumAdvanceBookingDays: number;
    canAuthorizeEmergencyBooking: boolean;
}) {
    const form = useForm({
        customer_name: booking.customer_name,
        customer_mobile: booking.customer_mobile ?? "",
        customer_email: booking.customer_email ?? "",
        event_date: booking.event_date,
        event_start_time: booking.event_start_time ?? "",
        venue_information: booking.venue_information ?? "",
        guest_count: booking.guest_count?.toString() ?? "",
        notes: booking.notes ?? "",
        follow_up_on: booking.follow_up_on ?? "",
    });
    const close = useForm({ status: "closed", reason: "" });
    const active = booking.status === "active";

    return (
        <AdminLayout
            title={`Pencil booking #${booking.id}`}
            description="This lead has no capacity hold. Conversion creates a normal reservation request; quotation, payment and confirmation rules still apply."
            section="requests"
        >
            {booking.converted_reservation_request && (
                <section className="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                    <h2 className="font-semibold text-emerald-950">
                        Converted reservation request
                    </h2>
                    <p className="mt-2 text-sm text-emerald-900">
                        This pencil booking is permanently linked to{" "}
                        {booking.converted_reservation_request.reference}.
                    </p>
                    <div className="mt-3 flex flex-wrap gap-4 text-sm font-semibold text-emerald-800">
                        <Link
                            href={`/admin/reservation-requests/${booking.converted_reservation_request.id}`}
                            className="underline"
                        >
                            View request
                        </Link>
                        {booking.converted_reservation_request.quotation && (
                            <Link
                                href={`/admin/quotations/${booking.converted_reservation_request.quotation.id}`}
                                className="underline"
                            >
                                View quotation
                            </Link>
                        )}
                    </div>
                </section>
            )}

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(`/admin/pencil-bookings/${booking.id}`);
                }}
                className="grid gap-3 rounded-2xl border bg-white p-5 sm:grid-cols-2"
            >
                <label>
                    Customer name
                    <input
                        value={form.data.customer_name}
                        onChange={(event) =>
                            form.setData("customer_name", event.target.value)
                        }
                        disabled={!canManage || !active}
                    />
                </label>
                <label>
                    Mobile
                    <input
                        value={form.data.customer_mobile ?? ""}
                        onChange={(event) =>
                            form.setData("customer_mobile", event.target.value)
                        }
                        disabled={!canManage || !active}
                    />
                </label>
                <label>
                    Email
                    <input
                        value={form.data.customer_email ?? ""}
                        onChange={(event) =>
                            form.setData("customer_email", event.target.value)
                        }
                        disabled={!canManage || !active}
                    />
                </label>
                <label>
                    Event date
                    <input
                        type="date"
                        value={form.data.event_date}
                        onChange={(event) =>
                            form.setData("event_date", event.target.value)
                        }
                        disabled={!canManage || !active}
                    />
                </label>
                <label>
                    Event start time
                    <input
                        type="time"
                        value={form.data.event_start_time}
                        onChange={(event) =>
                            form.setData("event_start_time", event.target.value)
                        }
                        disabled={!canManage || !active}
                    />
                </label>
                <label>
                    Venue
                    <input
                        value={form.data.venue_information}
                        onChange={(event) =>
                            form.setData(
                                "venue_information",
                                event.target.value,
                            )
                        }
                        disabled={!canManage || !active}
                    />
                </label>
                <label>
                    Estimated guests
                    <input
                        type="number"
                        min="1"
                        value={form.data.guest_count}
                        onChange={(event) =>
                            form.setData("guest_count", event.target.value)
                        }
                        disabled={!canManage || !active}
                    />
                </label>
                <label>
                    Follow up
                    <input
                        type="date"
                        value={form.data.follow_up_on ?? ""}
                        onChange={(event) =>
                            form.setData("follow_up_on", event.target.value)
                        }
                        disabled={!canManage || !active}
                    />
                </label>
                <label className="sm:col-span-2">
                    Notes
                    <textarea
                        value={form.data.notes}
                        onChange={(event) =>
                            form.setData("notes", event.target.value)
                        }
                        disabled={!canManage || !active}
                    />
                </label>
                {canManage && active && (
                    <button className="w-fit rounded-lg bg-emerald-800 px-4 py-2 text-white">
                        Save changes
                    </button>
                )}
            </form>

            {canManage && active && (
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        close.post(
                            `/admin/pencil-bookings/${booking.id}/close`,
                        );
                    }}
                    className="mt-6 rounded-2xl border bg-white p-5"
                >
                    <h2 className="font-semibold">Close lead</h2>
                    <div className="mt-3 flex flex-wrap gap-3">
                        <select
                            value={close.data.status}
                            onChange={(event) =>
                                close.setData("status", event.target.value)
                            }
                        >
                            <option value="closed">Closed</option>
                            <option value="did_not_proceed">
                                Did Not Proceed
                            </option>
                        </select>
                        <textarea
                            value={close.data.reason}
                            onChange={(event) =>
                                close.setData("reason", event.target.value)
                            }
                            placeholder="Outcome reason"
                        />
                        <button className="rounded-lg border px-3 py-2">
                            Record outcome
                        </button>
                    </div>
                </form>
            )}

            {canManage && active && !canCreateRequest && (
                <p className="mt-6 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950">
                    Your account can manage this pencil booking, but does not
                    have permission to create reservation requests. Ask an
                    Administrator to grant the relevant request-management
                    access or complete the conversion.
                </p>
            )}

            {canConvert && catalog && idempotencyKey && (
                <section className="mt-6 border-t pt-6">
                    <InternalRequestForm
                        key={`${booking.id}-${form.data.customer_name}-${form.data.customer_mobile}-${form.data.event_date}-${form.data.event_start_time}-${form.data.venue_information}`}
                        catalog={catalog}
                        endpoint={`/admin/pencil-bookings/${booking.id}/convert`}
                        idempotencyKey={idempotencyKey}
                        minimumAdvanceBookingDays={minimumAdvanceBookingDays}
                        canAuthorizeEmergencyBooking={
                            canAuthorizeEmergencyBooking
                        }
                        title="Convert to reservation request"
                        description="Confirm the lead details, then configure one Main Catering package and optional add-ons. This does not reserve capacity or confirm the event."
                        initial={{
                            full_name: form.data.customer_name,
                            mobile_number: form.data.customer_mobile,
                            email: form.data.customer_email,
                            event_date: form.data.event_date,
                            event_start_time: form.data.event_start_time,
                            venue_information: form.data.venue_information,
                            event_notes: form.data.notes,
                        }}
                    />
                </section>
            )}

            <section className="mt-6">
                <h2 className="font-semibold">Lifecycle history</h2>
                <ol className="mt-3 space-y-2">
                    {booking.events.map((event) => (
                        <li className="rounded-lg bg-white p-3" key={event.id}>
                            {event.event_type.replaceAll("_", " ")} ·{" "}
                            {formatManilaTimestamp(event.created_at)}
                            {event.actor ? ` · ${event.actor.name}` : ""}
                        </li>
                    ))}
                </ol>
            </section>
        </AdminLayout>
    );
}
