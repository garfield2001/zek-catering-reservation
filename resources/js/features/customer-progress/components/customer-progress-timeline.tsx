type PaymentSummary = {
    reservation_payment_satisfied: boolean;
    verified_minor: number;
} | null;

type CustomerProgressTimelineProps = {
    requestStatus: string;
    reservationStatus: "confirmed" | "completed" | "cancelled" | null;
    quotation: {
        status: string;
        accepted: boolean;
        paymentSummary: PaymentSummary;
        paymentStatuses: string[];
    } | null;
    cancellationStatuses: string[];
};

type TimelineItem = {
    label: string;
    detail: string;
    tone: "complete" | "current" | "upcoming" | "attention";
};

const labels: Record<string, string> = {
    submitted: "Inquiry received",
    under_review: "Staff review in progress",
    closed: "Inquiry closed",
    pending_review: "Request under review",
    refund_under_review: "Refund under review",
    refund_approved: "Refund approved",
    declined: "Request declined",
};

function humanize(value: string): string {
    return labels[value] ?? value.replaceAll("_", " ");
}

function paymentItem(
    quotation: CustomerProgressTimelineProps["quotation"],
): TimelineItem {
    if (!quotation?.accepted) {
        return {
            label: "Payment receipt",
            detail: quotation
                ? "Upload the first receipt to accept the displayed quotation."
                : "Available after a quotation is issued.",
            tone: "upcoming",
        };
    }

    if (quotation.paymentSummary?.reservation_payment_satisfied) {
        return {
            label: "Reservation payment verified",
            detail: "The required reservation payment is verified. Availability still needs Administrator review.",
            tone: "complete",
        };
    }

    if (
        quotation.paymentSummary &&
        quotation.paymentSummary.verified_minor > 0
    ) {
        return {
            label: "Partial payment verified",
            detail: "More verified payment is needed before the reservation-payment requirement is satisfied.",
            tone: "current",
        };
    }

    if (quotation.paymentStatuses.includes("pending_verification")) {
        return {
            label: "Receipt submitted",
            detail: "Your receipt is awaiting Administrator verification.",
            tone: "current",
        };
    }

    return {
        label: "Payment receipt needed",
        detail: "Your accepted quotation needs a receipt before payment can be reviewed.",
        tone: "current",
    };
}

function reservationItem(
    reservationStatus: CustomerProgressTimelineProps["reservationStatus"],
    quotation: CustomerProgressTimelineProps["quotation"],
    cancellationStatuses: string[],
): TimelineItem {
    if (reservationStatus === "completed") {
        return {
            label: "Event completed",
            detail: "Your catering event has been marked completed.",
            tone: "complete",
        };
    }

    if (reservationStatus === "cancelled") {
        return {
            label: "Reservation cancelled",
            detail: "Review any cancellation or refund updates below.",
            tone: "attention",
        };
    }

    const cancellation = cancellationStatuses.find((status) =>
        ["pending_review", "refund_under_review", "refund_approved"].includes(
            status,
        ),
    );
    if (cancellation) {
        return {
            label: humanize(cancellation),
            detail: "Your reservation remains subject to the Administrator’s written decision.",
            tone: "attention",
        };
    }

    if (reservationStatus === "confirmed") {
        return {
            label: "Reservation confirmed",
            detail: "Your event date has passed Administrator availability review.",
            tone: "complete",
        };
    }

    if (quotation?.paymentSummary?.reservation_payment_satisfied) {
        return {
            label: "Awaiting confirmation",
            detail: "Payment readiness is met, but only an Administrator can confirm availability and reserve capacity.",
            tone: "current",
        };
    }

    return {
        label: "Reservation confirmation",
        detail: "Available after quotation acceptance, payment readiness, and Administrator availability review.",
        tone: "upcoming",
    };
}

export function CustomerProgressTimeline({
    requestStatus,
    reservationStatus,
    quotation,
    cancellationStatuses,
}: CustomerProgressTimelineProps) {
    const quoteAvailable = quotation !== null;
    const reviewComplete = quoteAvailable || requestStatus === "closed";
    const cancellation = cancellationStatuses.find((status) =>
        ["pending_review", "refund_under_review", "refund_approved"].includes(
            status,
        ),
    );
    const items: TimelineItem[] = [
        {
            label: "Inquiry received",
            detail: "Your event details and package choices were sent to ZEK Catering.",
            tone: "complete",
        },
        {
            label:
                requestStatus === "under_review"
                    ? "Staff review in progress"
                    : "Staff review",
            detail:
                requestStatus === "under_review"
                    ? "Our team is reviewing your tentative event details."
                    : reviewComplete
                      ? "Your inquiry has been reviewed."
                      : `Current inquiry status: ${humanize(requestStatus)}.`,
            tone:
                requestStatus === "under_review"
                    ? "current"
                    : reviewComplete
                      ? "complete"
                      : "upcoming",
        },
        {
            label: quoteAvailable
                ? quotation.accepted
                    ? "Quotation accepted"
                    : "Quotation available"
                : "Quotation",
            detail: quoteAvailable
                ? quotation.accepted
                    ? "The current quotation was accepted through receipt submission."
                    : "Review the current quotation and its deadline below."
                : "A quotation is prepared only after Staff review.",
            tone: quoteAvailable ? "complete" : "upcoming",
        },
        paymentItem(quotation),
        reservationItem(reservationStatus, quotation, cancellationStatuses),
    ];

    return (
        <section
            className="mt-7"
            aria-labelledby="request-progress"
            aria-live="polite"
        >
            <h2 id="request-progress" className="sr-only">
                Request progress
            </h2>
            <ol className="grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
                {items.map((item, index) => (
                    <li
                        key={`${index}-${item.label}`}
                        className={`rounded-xl border p-3 text-sm ${
                            item.tone === "complete"
                                ? "border-emerald-800 bg-emerald-800 text-white"
                                : item.tone === "current"
                                  ? "border-amber-300 bg-amber-50 text-amber-950"
                                  : item.tone === "attention"
                                    ? "border-rose-200 bg-rose-50 text-rose-950"
                                    : "border-stone-200 bg-stone-100 text-stone-600"
                        }`}
                        aria-current={
                            item.tone === "current" ? "step" : undefined
                        }
                    >
                        <span className="block text-xs font-bold uppercase tracking-wide opacity-80">
                            Step {index + 1}
                        </span>
                        <strong className="mt-1 block">{item.label}</strong>
                        <span className="mt-1 block text-xs leading-5 opacity-90">
                            {item.detail}
                        </span>
                    </li>
                ))}
            </ol>
            {cancellation && (
                <p className="mt-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-950">
                    Current request update:{" "}
                    <strong>{humanize(cancellation)}.</strong> This does not by
                    itself cancel a reservation or approve a refund.
                </p>
            )}
        </section>
    );
}
