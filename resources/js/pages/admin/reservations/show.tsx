import { Link, useForm } from "@inertiajs/react";
import type { FormEvent } from "react";
import { formatPesos } from "../../../features/packages/money";
import {
    CancellationManagement,
    type CancellationRecord,
} from "../../../features/cancellations/components/cancellation-management";
import {
    CommercialChangeAddonConfigurator,
    type AddonSelection,
    type ChangeAddonOffering,
    type MenuSelection,
} from "../../../features/reservations/components/commercial-change-configurator";
import { PackageConfigurator } from "../../../features/storefront/components/package-configurator";
import type { PublicPackage } from "../../../features/storefront/types";
import AdminLayout from "../../../layouts/admin-layout";
import {
    formatManilaEvent,
    formatManilaTimestamp,
} from "../../../lib/date-time";

type ConfirmationEvent = {
    id: number;
    outcome: "confirmed" | "deferred" | "declined";
    reason: string | null;
    version_number: number;
    outstanding_balance_at_decision: boolean;
    created_at: string;
    actor: string;
};

type Reservation = {
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
    change_requests: Array<ReservationChangeRequest>;
};

type ReservationChangeRequest = {
    id: number;
    status:
        | "pending_review"
        | "awaiting_customer_agreement"
        | "awaiting_reconfirmation"
        | "applied"
        | "declined";
    change_types: string[];
    original_snapshot: Record<string, unknown>;
    proposed_snapshot: Record<string, unknown>;
    request_note: string | null;
    review_note: string | null;
    exception_reason: string | null;
    requires_quotation_revision: boolean;
    quotation_version: {
        id: number;
        version_number: number;
        status: string;
        total_minor: string;
    } | null;
    requested_at: string;
    reviewed_at: string | null;
    applied_at: string | null;
    requester: string | null;
    reviewer: string | null;
    applier: string | null;
};

type Props = {
    quotation: {
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
        reservation: Reservation | null;
    };
    paymentReadiness: {
        reservation_payment_satisfied: boolean;
        reservation_payment_overdue: boolean;
        unresolved_financial_obligations: boolean;
        reservation_required_minor?: number;
        verified_minor?: number;
        reservation_remaining_minor?: number;
        remaining_minor?: number;
    } | null;
    capacity: {
        event_date: string;
        maximum_confirmed_events: number;
        maximum_guests: number | null;
        confirmed_events: number;
        confirmed_guests: number;
        source: string;
    } | null;
    confirmationEvents: ConfirmationEvent[];
    canConfirm: boolean;
    canComplete: boolean;
    canManageCapacity: boolean;
    canRequestChange: boolean;
    canReviewChange: boolean;
    changeMenuOffering: {
        offering: PublicPackage;
        quantity: number;
        groups: Array<{
            quantity: number;
            selections: Record<string, unknown>;
        }>;
    } | null;
    changeAddonOfferings: ChangeAddonOffering[];
    requiresReconfirmation: boolean;
    cancellations: CancellationRecord[];
    verifiedPayments: Array<{ id: number; amount_minor: string }>;
    canRequestCancellation: boolean;
    canReviewCancellation: boolean;
};

export default function ReservationShow({
    quotation,
    paymentReadiness,
    capacity,
    confirmationEvents,
    canConfirm,
    canComplete,
    canManageCapacity,
    canRequestChange,
    canReviewChange,
    changeMenuOffering,
    changeAddonOfferings,
    requiresReconfirmation,
    cancellations,
    verifiedPayments,
    canRequestCancellation,
    canReviewCancellation,
}: Props) {
    const reservation = quotation.reservation;
    const paymentSatisfied = paymentReadiness?.reservation_payment_satisfied;
    const eventDate = reservation?.event_date ?? quotation.event_date;
    const eventStartTime =
        reservation?.event_start_time ?? quotation.event_start_time;
    const venueInformation =
        reservation?.venue_information ?? quotation.venue_information;

    return (
        <AdminLayout
            title={`Review ${quotation.reference}`}
            description="Confirmation is an Administrator decision. Accepted quotations and verified payments do not reserve capacity until this review is completed."
            section="reservations"
        >
            <div className="mb-5 flex flex-wrap gap-3 text-sm">
                <Link
                    href="/admin/reservations"
                    className="font-semibold text-emerald-800 underline"
                >
                    Back to confirmation queue
                </Link>
                <Link
                    href={`/admin/quotations/${quotation.id}`}
                    className="font-semibold text-emerald-800 underline"
                >
                    View quotation and payments
                </Link>
                {canManageCapacity && (
                    <Link
                        href="/admin/reservations/capacity"
                        className="font-semibold text-emerald-800 underline"
                    >
                        Manage date capacity
                    </Link>
                )}
            </div>

            <div className="grid gap-6 lg:grid-cols-2">
                <section className="rounded-2xl border bg-white p-5">
                    <h2 className="font-semibold">
                        {reservation ? "Confirmed event" : "Event to review"}
                    </h2>
                    <dl className="mt-3 space-y-3 text-sm">
                        <Detail
                            label="Customer"
                            value={quotation.customer_name}
                        />
                        <Detail
                            label="Event"
                            value={formatManilaEvent(eventDate, eventStartTime)}
                        />
                        <Detail
                            label="Venue"
                            value={venueInformation ?? "Not set"}
                        />
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
                <PaymentReadiness readiness={paymentReadiness} />
            </div>

            <CapacityCard capacity={capacity} />

            {reservation ? (
                <ReservationStatus
                    reservation={reservation}
                    requiresReconfirmation={requiresReconfirmation}
                />
            ) : canConfirm ? (
                <ConfirmationActions
                    quotation={quotation}
                    paymentSatisfied={paymentSatisfied === true}
                />
            ) : (
                <section className="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-5 text-sm text-slate-700">
                    You can review this confirmation, but only an Administrator
                    can commit capacity or record a decision.
                </section>
            )}

            {reservation &&
                canComplete &&
                reservation.status === "confirmed" && (
                    <CompletionForm reservation={reservation} />
                )}

            {reservation && (
                <ReservationChangeRequests
                    reservation={reservation}
                    quotationId={quotation.id}
                    canRequestChange={canRequestChange}
                    canReviewChange={canReviewChange}
                    changeMenuOffering={changeMenuOffering}
                    changeAddonOfferings={changeAddonOfferings}
                />
            )}

            <CancellationManagement
                quotationId={quotation.id}
                canRequest={canRequestCancellation}
                canReview={canReviewCancellation}
                cancellations={cancellations}
                verifiedPayments={verifiedPayments}
            />

            <DecisionHistory
                confirmationEvents={confirmationEvents}
                reservation={reservation}
            />
        </AdminLayout>
    );
}

function ReservationChangeRequests({
    reservation,
    quotationId,
    canRequestChange,
    canReviewChange,
    changeMenuOffering,
    changeAddonOfferings,
}: {
    reservation: Reservation;
    quotationId: number;
    canRequestChange: boolean;
    canReviewChange: boolean;
    changeMenuOffering: Props["changeMenuOffering"];
    changeAddonOfferings: ChangeAddonOffering[];
}) {
    const request = useForm({
        idempotency_key: "",
        event_date: "",
        event_start_time: "",
        venue_information: "",
        guest_count: "",
        menu_selection: null as MenuSelection | null,
        addon_selection: null as AddonSelection | null,
        request_note: "",
    });

    return (
        <section className="mt-6 rounded-2xl border bg-white p-5">
            <h2 className="font-semibold">Reservation changes</h2>
            <p className="mt-1 text-sm text-slate-600">
                Changes are reviewed separately. A confirmed reservation keeps
                its date, capacity, accepted quotation, and payments until an
                Administrator completes re-confirmation.
            </p>
            {canRequestChange && reservation.status === "confirmed" && (
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        request.transform((data) => ({
                            ...data,
                            idempotency_key: crypto.randomUUID(),
                            event_date: data.event_date || null,
                            event_start_time: data.event_start_time || null,
                            venue_information: data.venue_information || null,
                            guest_count: data.guest_count || null,
                        }));
                        request.post(
                            `/admin/reservations/${reservation.id}/change-requests`,
                            { preserveScroll: true },
                        );
                    }}
                    className="mt-5 grid gap-4 border-t pt-5 sm:grid-cols-2"
                >
                    <p className="sm:col-span-2 text-sm font-medium text-slate-800">
                        Prepare a change request for a customer who contacted
                        the team directly. Leave an unchanged field blank.
                    </p>
                    <ChangeInput
                        label="Proposed date"
                        type="date"
                        value={request.data.event_date}
                        onChange={(value) =>
                            request.setData("event_date", value)
                        }
                        error={request.errors.event_date}
                    />
                    <ChangeInput
                        label="Proposed start time"
                        type="time"
                        value={request.data.event_start_time}
                        onChange={(value) =>
                            request.setData("event_start_time", value)
                        }
                        error={request.errors.event_start_time}
                    />
                    <ChangeInput
                        label="Proposed venue"
                        value={request.data.venue_information}
                        onChange={(value) =>
                            request.setData("venue_information", value)
                        }
                        error={request.errors.venue_information}
                    />
                    <ChangeInput
                        label="Proposed guest count"
                        type="number"
                        value={request.data.guest_count}
                        onChange={(value) =>
                            request.setData("guest_count", value)
                        }
                        error={request.errors.guest_count}
                    />
                    {changeMenuOffering && (
                        <details className="sm:col-span-2 rounded-xl border border-slate-200 p-3">
                            <summary className="cursor-pointer font-semibold text-emerald-950">
                                Propose Main Catering menu changes
                            </summary>
                            <p className="mt-2 text-sm text-slate-600">
                                Select a complete proposed menu. Laravel checks
                                today&apos;s published package rules and prices
                                it again before the request is recorded.
                            </p>
                            <PackageConfigurator
                                offering={changeMenuOffering.offering}
                                previewUrl={`/catering-packages/${changeMenuOffering.offering.id}/preview`}
                                initialQuantity={
                                    Number(request.data.guest_count) ||
                                    changeMenuOffering.quantity
                                }
                                initialGroups={changeMenuOffering.groups}
                                selectionLabel="Use proposed menu"
                                onConfigurationSaved={(configuration) =>
                                    request.setData("menu_selection", {
                                        groups: configuration.groups,
                                    })
                                }
                            />
                            {request.data.menu_selection && (
                                <p className="mt-3 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-950">
                                    Proposed menu is ready to submit for review.
                                </p>
                            )}
                            <FieldError
                                error={
                                    (
                                        request.errors as Record<
                                            string,
                                            string | undefined
                                        >
                                    ).menu_selection
                                }
                            />
                        </details>
                    )}
                    {changeAddonOfferings.length > 0 && (
                        <div className="sm:col-span-2">
                            <CommercialChangeAddonConfigurator
                                offerings={changeAddonOfferings}
                                selection={request.data.addon_selection}
                                onChange={(selection) =>
                                    request.setData(
                                        "addon_selection",
                                        selection,
                                    )
                                }
                            />
                            <FieldError
                                error={
                                    (
                                        request.errors as Record<
                                            string,
                                            string | undefined
                                        >
                                    ).addon_selection
                                }
                            />
                        </div>
                    )}
                    <label className="sm:col-span-2 text-sm font-medium">
                        Customer request note
                        <textarea
                            value={request.data.request_note}
                            onChange={(event) =>
                                request.setData(
                                    "request_note",
                                    event.target.value,
                                )
                            }
                            className="mt-1 min-h-20 w-full"
                        />
                        <FieldError error={request.errors.request_note} />
                    </label>
                    <FieldError
                        error={
                            (
                                request.errors as Record<
                                    string,
                                    string | undefined
                                >
                            ).changes
                        }
                    />
                    <button
                        disabled={request.processing}
                        className="w-fit rounded-lg border border-emerald-800 px-4 py-2 text-sm font-semibold text-emerald-900 disabled:opacity-50"
                    >
                        Submit for Administrator review
                    </button>
                </form>
            )}
            {reservation.change_requests.length === 0 ? (
                <p className="mt-5 text-sm text-slate-600">
                    No reservation changes have been requested.
                </p>
            ) : (
                <ol className="mt-5 space-y-4">
                    {reservation.change_requests.map((change) => (
                        <ChangeRequestCard
                            key={change.id}
                            change={change}
                            quotationId={quotationId}
                            canReviewChange={canReviewChange}
                        />
                    ))}
                </ol>
            )}
        </section>
    );
}

function ChangeInput({
    label,
    type = "text",
    value,
    onChange,
    error,
}: {
    label: string;
    type?: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
}) {
    const id = `change-${label.toLowerCase().replaceAll(" ", "-")}`;

    return (
        <label className="text-sm font-medium" htmlFor={id}>
            {label}
            <input
                id={id}
                type={type}
                min={type === "number" ? 1 : undefined}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                aria-invalid={Boolean(error)}
                className="mt-1 w-full"
            />
            <FieldError error={error} />
        </label>
    );
}

function ChangeRequestCard({
    change,
    quotationId,
    canReviewChange,
}: {
    change: ReservationChangeRequest;
    quotationId: number;
    canReviewChange: boolean;
}) {
    const review = useForm({
        approved: true,
        review_note: "",
        exception_reason: "",
    });
    const apply = useForm({});
    const changedFacts = change.change_types.map((type) => {
        const key =
            type === "event_start_time"
                ? "event_start_time"
                : type === "venue_information"
                  ? "venue_information"
                  : type === "guest_count"
                    ? "guest_count"
                    : type;
        return {
            label: type.replaceAll("_", " "),
            before: String(change.original_snapshot[key] ?? "Not set"),
            after: String(change.proposed_snapshot[key] ?? "Not set"),
        };
    });

    return (
        <li className="rounded-xl border border-slate-200 p-4 text-sm">
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <h3 className="font-semibold">Change request #{change.id}</h3>
                <span className="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold capitalize">
                    {change.status.replaceAll("_", " ")}
                </span>
            </div>
            <p className="mt-1 text-slate-600">
                Submitted by {change.requester ?? "Customer"} on{" "}
                {formatManilaTimestamp(change.requested_at)}.
                {change.request_note ? ` ${change.request_note}` : ""}
            </p>
            <dl className="mt-3 grid gap-2 sm:grid-cols-2">
                {changedFacts.map((fact) => (
                    <div key={fact.label} className="rounded bg-slate-50 p-2">
                        <dt className="capitalize text-slate-500">
                            {fact.label}
                        </dt>
                        <dd>
                            <span className="line-through">{fact.before}</span>{" "}
                            → {fact.after}
                        </dd>
                    </div>
                ))}
            </dl>
            {change.requires_quotation_revision && (
                <p className="mt-3 rounded bg-amber-50 p-3 text-amber-950">
                    This commercial change requires a quotation revision and
                    customer agreement. Payments are never moved automatically.
                </p>
            )}
            {change.quotation_version && (
                <p className="mt-3 text-slate-700">
                    Revision v{change.quotation_version.version_number}:{" "}
                    {change.quotation_version.status}.{" "}
                    <Link
                        href={`/admin/quotations/${quotationId}`}
                        className="font-semibold text-emerald-800 underline"
                    >
                        Open quotation
                    </Link>
                </p>
            )}
            {change.review_note && (
                <p className="mt-3 text-slate-700">
                    Review: {change.review_note}
                </p>
            )}
            {change.exception_reason && (
                <p className="mt-1 text-amber-800">
                    Exception: {change.exception_reason}
                </p>
            )}
            {canReviewChange && change.status === "pending_review" && (
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        review.post(
                            `/admin/reservation-change-requests/${change.id}/review`,
                            { preserveScroll: true },
                        );
                    }}
                    className="mt-4 grid gap-3 border-t pt-4"
                >
                    <label className="text-sm font-medium">
                        Decision
                        <select
                            value={String(review.data.approved)}
                            onChange={(event) =>
                                review.setData(
                                    "approved",
                                    event.target.value === "true",
                                )
                            }
                            className="mt-1 w-full"
                        >
                            <option value="true">Approve for next step</option>
                            <option value="false">Decline request</option>
                        </select>
                    </label>
                    <label className="text-sm font-medium">
                        Review note{" "}
                        {review.data.approved
                            ? "(optional)"
                            : "(required to decline)"}
                        <textarea
                            value={review.data.review_note}
                            onChange={(event) =>
                                review.setData(
                                    "review_note",
                                    event.target.value,
                                )
                            }
                            className="mt-1 min-h-20 w-full"
                            aria-invalid={Boolean(review.errors.review_note)}
                        />
                        <FieldError error={review.errors.review_note} />
                    </label>
                    <label className="text-sm font-medium">
                        Exception reason (required for short-notice reschedules
                        or late menu changes)
                        <textarea
                            value={review.data.exception_reason}
                            onChange={(event) =>
                                review.setData(
                                    "exception_reason",
                                    event.target.value,
                                )
                            }
                            className="mt-1 min-h-20 w-full"
                            aria-invalid={Boolean(
                                review.errors.exception_reason,
                            )}
                        />
                        <FieldError error={review.errors.exception_reason} />
                    </label>
                    <FieldError
                        error={
                            (
                                review.errors as Record<
                                    string,
                                    string | undefined
                                >
                            ).change_request
                        }
                    />
                    <button
                        disabled={review.processing}
                        className="w-fit rounded-lg bg-emerald-800 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                    >
                        Save Administrator decision
                    </button>
                </form>
            )}
            {canReviewChange && change.status === "awaiting_reconfirmation" && (
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        apply.post(
                            `/admin/reservation-change-requests/${change.id}/apply`,
                            { preserveScroll: true },
                        );
                    }}
                    className="mt-4 border-t pt-4"
                >
                    <p className="mb-3 text-slate-600">
                        Apply only after payment readiness and capacity can be
                        re-confirmed. Any failure leaves the existing
                        reservation untouched.
                    </p>
                    <FieldError
                        error={
                            (apply.errors as Record<string, string | undefined>)
                                .payment ??
                            (apply.errors as Record<string, string | undefined>)
                                .availability ??
                            (apply.errors as Record<string, string | undefined>)
                                .change_request
                        }
                    />
                    <button
                        disabled={apply.processing}
                        className="rounded-lg bg-emerald-800 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                    >
                        Re-confirm and apply change
                    </button>
                </form>
            )}
        </li>
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

function PaymentReadiness({
    readiness,
}: {
    readiness: Props["paymentReadiness"];
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
                    ? "Verified reservation-payment requirement satisfied."
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

function CapacityCard({ capacity }: { capacity: Props["capacity"] }) {
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

function ConfirmationActions({
    quotation,
    paymentSatisfied,
}: {
    quotation: Props["quotation"];
    paymentSatisfied: boolean;
}) {
    const confirm = useForm({
        guest_count: String(quotation.request_guest_count ?? ""),
    });
    const decision = useForm({
        outcome: "deferred" as "deferred" | "declined",
        reason: "",
    });
    const confirmErrors = confirm.errors as Record<string, string | undefined>;
    const decisionErrors = decision.errors as Record<
        string,
        string | undefined
    >;

    const submitConfirm = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        confirm.post(`/admin/reservations/quotations/${quotation.id}/confirm`, {
            preserveScroll: true,
            onError: () =>
                document
                    .querySelector("[aria-invalid='true']")
                    ?.scrollIntoView({ behavior: "smooth", block: "center" }),
        });
    };

    return (
        <div className="mt-6 grid gap-6 lg:grid-cols-2">
            <form
                onSubmit={submitConfirm}
                className="rounded-2xl border border-emerald-200 bg-emerald-50 p-5"
            >
                <h2 className="font-semibold text-emerald-950">
                    Confirm reservation
                </h2>
                <p className="mt-1 text-sm text-emerald-900">
                    This commits one event-capacity slot only after the latest
                    accepted version, verified payment threshold, and capacity
                    are checked again.
                </p>
                {!paymentSatisfied && (
                    <p role="alert" className="mt-3 text-sm text-rose-800">
                        Confirmation will remain blocked until the verified
                        reservation-payment requirement is satisfied.
                    </p>
                )}
                <label className="mt-4 block text-sm font-medium">
                    Expected guests
                    <input
                        type="number"
                        min="1"
                        value={confirm.data.guest_count}
                        onChange={(event) =>
                            confirm.setData("guest_count", event.target.value)
                        }
                        aria-invalid={Boolean(confirm.errors.guest_count)}
                        aria-describedby={
                            confirm.errors.guest_count
                                ? "reservation-guest-count-error"
                                : undefined
                        }
                        className="mt-1 w-full"
                    />
                    <span className="mt-1 block text-xs font-normal text-slate-600">
                        The inquiry guest count is used when available. Enter
                        one for a direct quotation if a guest capacity applies.
                    </span>
                </label>
                <FieldError
                    id="reservation-guest-count-error"
                    error={confirmErrors.guest_count}
                />
                <FieldError error={confirmErrors.event_date} />
                <FieldError error={confirmErrors.payment} />
                <FieldError error={confirmErrors.availability} />
                <FieldError error={confirmErrors.quotation} />
                <FieldError error={confirmErrors.reservation} />
                <button
                    disabled={confirm.processing || !paymentSatisfied}
                    className="mt-5 rounded-lg bg-emerald-800 px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                >
                    Confirm reservation
                </button>
            </form>
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    decision.post(
                        `/admin/reservations/quotations/${quotation.id}/outcome`,
                        { preserveScroll: true },
                    );
                }}
                className="rounded-2xl border bg-white p-5"
            >
                <h2 className="font-semibold">Defer or decline confirmation</h2>
                <p className="mt-1 text-sm text-slate-600">
                    This records an Administrator review decision without
                    changing payments or reserving capacity.
                </p>
                <label className="mt-4 block text-sm font-medium">
                    Decision
                    <select
                        value={decision.data.outcome}
                        onChange={(event) =>
                            decision.setData(
                                "outcome",
                                event.target.value as "deferred" | "declined",
                            )
                        }
                        className="mt-1 w-full"
                    >
                        <option value="deferred">Defer confirmation</option>
                        <option value="declined">Decline confirmation</option>
                    </select>
                </label>
                <label className="mt-4 block text-sm font-medium">
                    Reason
                    <textarea
                        required
                        value={decision.data.reason}
                        onChange={(event) =>
                            decision.setData("reason", event.target.value)
                        }
                        aria-invalid={Boolean(decisionErrors.reason)}
                        aria-describedby={
                            decisionErrors.reason
                                ? "confirmation-reason-error"
                                : undefined
                        }
                        className="mt-1 min-h-24 w-full"
                    />
                </label>
                <FieldError
                    id="confirmation-reason-error"
                    error={decisionErrors.reason}
                />
                <FieldError error={decisionErrors.outcome} />
                <FieldError error={decisionErrors.reservation} />
                <FieldError error={decisionErrors.quotation} />
                <button
                    disabled={decision.processing}
                    className="mt-5 rounded-lg border border-slate-400 px-4 py-2 text-sm font-semibold text-slate-800 disabled:opacity-50"
                >
                    Record decision
                </button>
            </form>
        </div>
    );
}

function ReservationStatus({
    reservation,
    requiresReconfirmation,
}: {
    reservation: Reservation;
    requiresReconfirmation: boolean;
}) {
    return (
        <section className="mt-6 rounded-2xl border bg-white p-5">
            <h2 className="font-semibold">Reservation status</h2>
            <p className="mt-2 text-sm text-slate-700">
                <strong className="capitalize">{reservation.status}</strong>
                {" · "}
                {reservation.reference}
                {" · "}confirmed by {reservation.confirmed_by} on{" "}
                {formatManilaTimestamp(reservation.confirmed_at)}.
            </p>
            {requiresReconfirmation && (
                <p role="alert" className="mt-3 text-sm text-amber-800">
                    A newer accepted quotation version exists. This confirmed
                    reservation remains unchanged until an Administrator reviews
                    and re-confirms it.
                </p>
            )}
            {reservation.status === "completed" && (
                <p className="mt-3 text-sm text-emerald-800">
                    Completed by {reservation.completed_by} on{" "}
                    {formatManilaTimestamp(reservation.completed_at)}.
                    {reservation.completion_note
                        ? ` Note: ${reservation.completion_note}`
                        : ""}
                </p>
            )}
        </section>
    );
}

function CompletionForm({ reservation }: { reservation: Reservation }) {
    const form = useForm({ note: "" });
    const errors = form.errors as Record<string, string | undefined>;

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.post(`/admin/reservations/${reservation.id}/complete`, {
                    preserveScroll: true,
                });
            }}
            className="mt-6 rounded-2xl border bg-white p-5"
        >
            <h2 className="font-semibold">Mark event completed</h2>
            <p className="mt-1 text-sm text-slate-600">
                Use after the event. This preserves the confirmation and
                financial history.
            </p>
            <label className="mt-4 block text-sm font-medium">
                Completion note (optional)
                <textarea
                    value={form.data.note}
                    onChange={(event) =>
                        form.setData("note", event.target.value)
                    }
                    aria-invalid={Boolean(form.errors.note)}
                    className="mt-1 min-h-20 w-full"
                />
            </label>
            <FieldError error={errors.note} />
            <FieldError error={errors.reservation} />
            <button
                disabled={form.processing}
                className="mt-4 rounded-lg bg-emerald-800 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
            >
                Mark completed
            </button>
        </form>
    );
}

function DecisionHistory({
    confirmationEvents,
    reservation,
}: {
    confirmationEvents: ConfirmationEvent[];
    reservation: Reservation | null;
}) {
    return (
        <section className="mt-6 rounded-2xl border bg-white p-5">
            <h2 className="font-semibold">Confirmation history</h2>
            <ol className="mt-4 space-y-3 text-sm">
                {confirmationEvents.map((event) => (
                    <li key={event.id} className="rounded-lg bg-slate-50 p-3">
                        <strong className="capitalize">{event.outcome}</strong>
                        {" · "}quotation version {event.version_number}
                        {" · "}
                        {formatManilaTimestamp(event.created_at)} by{" "}
                        {event.actor}
                        {event.reason ? ` — ${event.reason}` : ""}
                        {event.outstanding_balance_at_decision && (
                            <span className="block mt-1 text-amber-800">
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
                {confirmationEvents.length === 0 &&
                    (reservation?.status_events.length ?? 0) === 0 && (
                        <li className="text-slate-600">
                            No confirmation decision has been recorded.
                        </li>
                    )}
            </ol>
        </section>
    );
}

function FieldError({ id, error }: { id?: string; error?: string }) {
    return error ? (
        <p id={id} role="alert" className="mt-2 text-sm text-rose-700">
            {error}
        </p>
    ) : null;
}
