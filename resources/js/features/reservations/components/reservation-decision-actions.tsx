import { useForm } from "@inertiajs/react";
import type { FormEvent } from "react";
import { formatManilaTimestamp } from "../../../lib/date-time";

type ConfirmationQuotation = {
    id: number;
    request_guest_count: number | null;
};

type ReservationForActions = {
    id: number;
    reference: string;
    status: "confirmed" | "completed" | "cancelled";
    confirmed_at: string;
    confirmed_by: string;
    completed_at: string | null;
    completed_by: string | null;
    completion_note: string | null;
};

export function ConfirmationActions({
    quotation,
    paymentSatisfied,
}: {
    quotation: ConfirmationQuotation;
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

export function ReservationStatus({
    reservation,
    requiresReconfirmation,
}: {
    reservation: ReservationForActions;
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

export function CompletionForm({
    reservation,
}: {
    reservation: ReservationForActions;
}) {
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

function FieldError({ id, error }: { id?: string; error?: string }) {
    return error ? (
        <p id={id} role="alert" className="mt-2 text-sm text-rose-700">
            {error}
        </p>
    ) : null;
}
