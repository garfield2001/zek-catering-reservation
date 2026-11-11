import { formatPesos } from "../../packages/money";
import {
    formatManilaEvent,
    formatManilaTimestamp,
} from "../../../lib/date-time";

export type ReservationReviewReservation = {
    id: number;
    reference: string;
    status: "confirmed" | "completed" | "cancelled";
    confirmed_quotation_version_id: number;
    confirmed_version_number: number;
    event_date: string;
    event_start_time: string | null;
    venue_information: string | null;
    guest_count: number | null;
    confirmed_at: string;
    confirmed_by: string;
    completed_at: string | null;
    completed_by: string | null;
    completion_note: string | null;
    capacity_snapshot: Record<string, unknown>;
    status_events: Array<{
        id: number;
        from_status: string | null;
        to_status: string;
        note: string | null;
        created_at: string;
        actor: string;
    }>;
};

export type ReservationReviewQuotation = {
    id: number;
    reference: string;
    customer_name: string;
    event_date: string | null;
    event_start_time: string | null;
    venue_information: string | null;
    origin: string;
    request_guest_count: number | null;
    latest_accepted_version: {
        id: number;
        version_number: number;
        total_minor: string;
        accepted_at: string;
    } | null;
    reservation: ReservationReviewReservation | null;
};

export type ReservationPaymentReadiness = {
    reservation_payment_satisfied: boolean;
    reservation_payment_overdue: boolean;
    unresolved_financial_obligations: boolean;
    reservation_required_minor?: number;
    verified_minor?: number;
    reservation_remaining_minor?: number;
    remaining_minor?: number;
};

export type ReservationCapacity = {
    event_date: string;
    maximum_confirmed_events: number;
    maximum_guests: number | null;
    confirmed_events: number;
    confirmed_guests: number;
    source: string;
};

export type ConfirmationDecision = {
    id: number;
    outcome: "confirmed" | "deferred" | "declined";
    reason: string | null;
    version_number: number;
    outstanding_balance_at_decision: boolean;
    created_at: string;
    actor: string;
};

export function ReservationEventSummary({
    quotation,
}: {
    quotation: ReservationReviewQuotation;
}) {
    const reservation = quotation.reservation;
    const eventDate = reservation?.event_date ?? quotation.event_date;
    const eventStartTime =
        reservation?.event_start_time ?? quotation.event_start_time;
    const venueInformation =
        reservation?.venue_information ?? quotation.venue_information;

    return (
        <section className="rounded-2xl border bg-white p-5">
            <h2 className="font-semibold">
                {reservation ? "Confirmed event" : "Event to review"}
            </h2>
            <dl className="mt-3 space-y-3 text-sm">
                <Detail label="Customer" value={quotation.customer_name} />
                <Detail
                    label="Event"
                    value={formatManilaEvent(eventDate, eventStartTime)}
                />
                <Detail label="Venue" value={venueInformation ?? "Not set"} />
                <Detail
                    label="Accepted quotation"
                    value={
                        quotation.latest_accepted_version
                            ? `Version ${quotation.latest_accepted_version.version_number} · ${formatPesos(quotation.latest_accepted_version.total_minor)}`
                            : "No accepted quotation version"
                    }
                />
            </dl>
        </section>
    );
}

export function PaymentReadinessCard({
    readiness,
}: {
    readiness: ReservationPaymentReadiness | null;
}) {
    if (!readiness) {
        return (
            <section className="rounded-2xl border bg-white p-5 text-sm text-slate-600">
                <h2 className="font-semibold text-slate-900">
                    Payment readiness
                </h2>
                <p className="mt-2">
                    Payment amounts require the Payment Records permission. An
                    Administrator can still review whether the threshold is
                    satisfied.
                </p>
            </section>
        );
    }

    return (
        <section className="rounded-2xl border bg-white p-5">
            <h2 className="font-semibold">Payment readiness</h2>
            <p
                className={`mt-2 text-sm font-medium ${readiness.reservation_payment_satisfied ? "text-emerald-800" : "text-rose-800"}`}
            >
                {readiness.reservation_payment_satisfied
                    ? "Verified reservation-payment requirement satisfied. Full payment may still remain outstanding."
                    : "The verified reservation-payment requirement is not yet satisfied."}
            </p>
            {readiness.reservation_payment_overdue && (
                <p className="mt-2 text-sm text-amber-800">
                    The payment deadline has passed. This history is retained
                    for review; it does not by itself block a now-satisfied
                    payment requirement.
                </p>
            )}
            {readiness.reservation_required_minor !== undefined && (
                <dl className="mt-4 grid gap-2 text-sm sm:grid-cols-2">
                    <Detail
                        label="Required"
                        value={formatPesos(
                            String(readiness.reservation_required_minor),
                        )}
                    />
                    <Detail
                        label="Verified"
                        value={formatPesos(
                            String(readiness.verified_minor ?? 0),
                        )}
                    />
                    <Detail
                        label="Requirement remaining"
                        value={formatPesos(
                            String(readiness.reservation_remaining_minor ?? 0),
                        )}
                    />
                    <Detail
                        label="Total balance remaining"
                        value={formatPesos(
                            String(readiness.remaining_minor ?? 0),
                        )}
                    />
                </dl>
            )}
        </section>
    );
}

export function CapacityAvailabilityCard({
    capacity,
}: {
    capacity: ReservationCapacity | null;
}) {
    if (!capacity) {
        return (
            <section className="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                An event date is required before availability can be checked or
                a reservation can be confirmed.
            </section>
        );
    }

    return (
        <section className="mt-6 rounded-2xl border bg-white p-5">
            <h2 className="font-semibold">Availability on this event date</h2>
            <p className="mt-1 text-sm text-slate-600">
                Current usage is checked again inside the confirmation
                transaction. This review does not hold a slot.
            </p>
            <div className="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                <Detail
                    label="Capacity-consuming events"
                    value={`${capacity.confirmed_events} of ${capacity.maximum_confirmed_events}`}
                />
                <Detail
                    label="Confirmed guests"
                    value={
                        capacity.maximum_guests === null
                            ? `${capacity.confirmed_guests}; no guest limit`
                            : `${capacity.confirmed_guests} of ${capacity.maximum_guests}`
                    }
                />
                <Detail label="Capacity source" value={capacity.source} />
            </div>
        </section>
    );
}

export function ReservationLifecycleHistory({
    decisions,
    reservation,
}: {
    decisions: ConfirmationDecision[];
    reservation: ReservationReviewReservation | null;
}) {
    return (
        <section className="mt-6 rounded-2xl border bg-white p-5">
            <h2 className="font-semibold">Confirmation history</h2>
            <ol className="mt-4 space-y-3 text-sm">
                {decisions.map((event) => (
                    <li key={event.id} className="rounded-lg bg-slate-50 p-3">
                        <strong className="capitalize">{event.outcome}</strong>
                        {" · "}quotation version {event.version_number}
                        {" · "}
                        {formatManilaTimestamp(event.created_at)} by{" "}
                        {event.actor}
                        {event.reason ? ` — ${event.reason}` : ""}
                        {event.outstanding_balance_at_decision && (
                            <span className="mt-1 block text-amber-800">
                                An outstanding balance existed when this
                                decision was recorded.
                            </span>
                        )}
                    </li>
                ))}
                {reservation?.status_events.map((event) => (
                    <li
                        key={`status-${event.id}`}
                        className="rounded-lg bg-slate-50 p-3"
                    >
                        <strong className="capitalize">
                            {event.to_status}
                        </strong>
                        {" · "}
                        {formatManilaTimestamp(event.created_at)} by{" "}
                        {event.actor}
                        {event.note ? ` — ${event.note}` : ""}
                    </li>
                ))}
                {decisions.length === 0 &&
                    (reservation?.status_events.length ?? 0) === 0 && (
                        <li className="text-slate-600">
                            No confirmation decision has been recorded.
                        </li>
                    )}
            </ol>
        </section>
    );
}

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-slate-500">{label}</dt>
            <dd className="mt-0.5 text-slate-900">{value}</dd>
        </div>
    );
}
