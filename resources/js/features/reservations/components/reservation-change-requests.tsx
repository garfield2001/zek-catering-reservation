import { Link, useForm } from "@inertiajs/react";
import {
    CommercialChangeAddonConfigurator,
    type AddonSelection,
    type ChangeAddonOffering,
    type MenuSelection,
} from "./commercial-change-configurator";
import { PackageConfigurator } from "../../storefront/components/package-configurator";
import type { PublicPackage } from "../../storefront/types";
import { formatManilaTimestamp } from "../../../lib/date-time";

export type ReservationChangeRequest = {
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

type ChangeableReservation = {
    id: number;
    status: "confirmed" | "completed" | "cancelled";
    change_requests: ReservationChangeRequest[];
};

export function ReservationChangeRequests({
    reservation,
    quotationId,
    canRequestChange,
    canReviewChange,
    changeMenuOffering,
    changeAddonOfferings,
}: {
    reservation: ChangeableReservation;
    quotationId: number;
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

function FieldError({ id, error }: { id?: string; error?: string }) {
    return error ? (
        <p id={id} role="alert" className="mt-2 text-sm text-rose-700">
            {error}
        </p>
    ) : null;
}
