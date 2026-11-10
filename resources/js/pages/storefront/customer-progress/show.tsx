import { router, useForm, usePage } from "@inertiajs/react";
import { PackageConfigurator } from "../../../features/storefront/components/package-configurator";
import { CustomerCancellationPanel } from "../../../features/cancellations/components/customer-cancellation";
import {
    CommercialChangeAddonConfigurator,
    type AddonSelection,
    type ChangeAddonOffering,
    type MenuSelection,
} from "../../../features/reservations/components/commercial-change-configurator";
import { formatPesos } from "../../../features/packages/money";
import {
    formatManilaEvent,
    formatManilaTime,
    formatManilaTimestamp,
} from "../../../lib/date-time";
import StorefrontLayout from "../../../layouts/storefront-layout";
import type { PublicPackage } from "../../../features/storefront/types";

type Quote = {
    id: number;
    reference: string;
    status: string;
    valid_until: string | null;
    line_snapshots: Array<{
        line_number: number;
        description?: string;
        line_type: string;
        quantity: number;
        total_minor: string;
    }>;
    total_minor: string;
    terms_snapshot: {
        reservation_payment?: {
            type: string;
            amount_minor: string | null;
            basis_points: number | null;
            payment_deadline_hours: number;
        };
    } | null;
    payment_instructions: {
        account_name: string;
        ewallet_number: string;
        bank_account: string;
        additional_instructions: string;
        contains_placeholders: boolean;
        updated_since_issuance: boolean;
        effective_at: string | null;
    };
    payment_upload_cutoff_at: string | null;
    payment_upload_open: boolean;
    acceptance: {
        accepted_at: string;
        payment_due_at: string;
        final_payment_due_at: string | null;
        menu_finalization_due_at: string;
    } | null;
    payment_summary: {
        total_minor: number;
        verified_minor: number;
        remaining_minor: number;
        reservation_required_minor: number;
        reservation_remaining_minor: number;
        overpayment_minor: number;
        reservation_payment_satisfied: boolean;
        reservation_payment_overdue: boolean;
        final_payment_overdue: boolean;
    } | null;
    payments: Array<{
        id: number;
        status: string;
        amount_minor: string | null;
        paid_at: string | null;
        method: string | null;
        rejection_reason: string | null;
        customer_message: string | null;
    }>;
};

type ProgressRequest = {
    reference: string;
    status: string;
    customer_name: string;
    event_date: string;
    event_start_time: string;
    event_end_time: string;
    venue_information: string;
    guest_count: number;
    estimated_subtotal_minor: string;
    reservation: {
        status: "confirmed" | "completed" | "cancelled";
        can_request_changes: boolean;
        change_requests: Array<{
            id: number;
            status: string;
            change_types: string[];
            requested_at: string;
            review_note: string | null;
            requires_quotation_revision: boolean;
            quotation_version: {
                id: number;
                version_number: number;
                status: string;
                total_minor: string;
                accepted: boolean;
            } | null;
        }>;
    } | null;
    cancellation: {
        can_request: boolean;
        guidance: string;
        notice_days: number;
        requests: Array<{
            id: number;
            kind: "withdrawal" | "cancellation";
            status: string;
            requested_at: string;
            refunds: Array<{
                id: number;
                amount_minor: string;
                completed_at: string | null;
            }>;
        }>;
    } | null;
    quotation: Quote | null;
};

export default function CustomerProgressShow({
    request,
    changeMenuOffering,
    changeAddonOfferings,
}: {
    request: ProgressRequest;
    changeMenuOffering: ChangeMenuOffering | null;
    changeAddonOfferings: ChangeAddonOffering[];
}) {
    const quote = request.quotation;
    const stage = quote?.acceptance
        ? 4
        : quote?.status === "issued"
          ? 3
          : request.status === "under_review"
            ? 2
            : 1;
    return (
        <StorefrontLayout title="My Catering Request">
            <div className="mx-auto max-w-3xl px-4 py-12 sm:px-6">
                <p className="text-xs font-bold uppercase tracking-[.16em] text-amber-700">
                    My Catering Request
                </p>
                <h1 className="mt-3 font-serif text-4xl font-bold text-emerald-950">
                    {request.reference}
                </h1>
                <ol className="mt-7 grid gap-2 sm:grid-cols-4">
                    {[
                        "Inquiry received",
                        "Under review",
                        "Quotation available",
                        "Payment receipt submitted",
                    ].map((label, index) => (
                        <li
                            key={label}
                            className={`rounded-lg p-3 text-sm ${index < stage ? "bg-emerald-800 text-white" : "bg-stone-100 text-stone-500"}`}
                        >
                            {index + 1}. {label}
                        </li>
                    ))}
                </ol>
                <section className="mt-7 rounded-2xl border bg-white p-5">
                    <h2 className="font-semibold">Your event</h2>
                    <p className="mt-3 text-sm">
                        {request.customer_name} ·{" "}
                        {formatManilaEvent(
                            request.event_date,
                            request.event_start_time,
                        )}{" "}
                        – {formatManilaTime(request.event_end_time)}
                    </p>
                    <p className="mt-1 text-sm text-stone-600">
                        {request.venue_information} · {request.guest_count}{" "}
                        guests
                    </p>
                </section>
                {request.reservation && (
                    <ReservationChanges
                        request={request}
                        changeMenuOffering={changeMenuOffering}
                        changeAddonOfferings={changeAddonOfferings}
                    />
                )}
                {request.cancellation && (
                    <CustomerCancellationPanel
                        reference={request.reference}
                        cancellation={request.cancellation}
                    />
                )}
                {quote ? (
                    <QuotationCard
                        quote={quote}
                        reference={request.reference}
                    />
                ) : (
                    <section className="mt-5 rounded-2xl border bg-amber-50 p-5">
                        <h2 className="font-semibold">Planning estimate</h2>
                        <p className="mt-2 text-2xl font-semibold">
                            {formatPesos(request.estimated_subtotal_minor)}
                        </p>
                        <p className="mt-2 text-sm text-stone-600">
                            Your request is{" "}
                            {request.status.replaceAll("_", " ")}. Staff will
                            prepare a quotation if appropriate. This is not a
                            confirmed booking or availability hold.
                        </p>
                    </section>
                )}
            </div>
        </StorefrontLayout>
    );
}

type ChangeMenuOffering = {
    offering: PublicPackage;
    quantity: number;
    groups: Array<{
        quantity: number;
        selections: Record<string, unknown>;
    }>;
};

type ReservationChangeForm = {
    idempotency_key: string;
    event_date: string;
    event_start_time: string;
    venue_information: string;
    guest_count: string;
    menu_selection: MenuSelection | null;
    addon_selection: AddonSelection | null;
    request_note: string;
};

function ReservationChanges({
    request,
    changeMenuOffering,
    changeAddonOfferings,
}: {
    request: ProgressRequest;
    changeMenuOffering: ChangeMenuOffering | null;
    changeAddonOfferings: ChangeAddonOffering[];
}) {
    const reservation = request.reservation;
    const form = useForm<ReservationChangeForm>({
        idempotency_key: "",
        event_date: request.event_date,
        event_start_time: request.event_start_time.slice(0, 5),
        venue_information: request.venue_information,
        guest_count: String(request.guest_count),
        menu_selection: null,
        addon_selection: null,
        request_note: "",
    });
    const errors = form.errors as Record<string, string | undefined>;

    if (!reservation) return null;

    return (
        <section className="mt-5 rounded-2xl border bg-white p-5">
            <h2 className="font-semibold">Reservation changes</h2>
            <p className="mt-1 text-sm text-stone-600">
                {reservation.status === "confirmed"
                    ? "A request never changes your confirmed event immediately. An Administrator reviews availability, pricing, and payment implications first."
                    : "This event is completed. Contact ZEK Catering for any historical-record correction."}
            </p>
            {reservation.change_requests.length > 0 && (
                <ul className="mt-4 space-y-2 text-sm">
                    {reservation.change_requests.map((change) => (
                        <li
                            key={change.id}
                            className="rounded-lg bg-stone-50 p-3"
                        >
                            <strong>Change request #{change.id}</strong>{" "}
                            {change.status.replaceAll("_", " ")} ·{" "}
                            {change.change_types
                                .map((type) => type.replaceAll("_", " "))
                                .join(", ")}
                            <span className="block text-stone-600">
                                Submitted{" "}
                                {formatManilaTimestamp(change.requested_at)}
                                {change.review_note
                                    ? ` — ${change.review_note}`
                                    : ""}
                            </span>
                            {change.status === "awaiting_customer_agreement" &&
                                change.quotation_version && (
                                    <RevisedQuotationAgreement
                                        request={request}
                                        change={change}
                                    />
                                )}
                            {change.status === "awaiting_reconfirmation" && (
                                <p className="mt-2 text-amber-900">
                                    Your agreement was recorded. An
                                    Administrator is re-checking payment
                                    readiness and event capacity; your current
                                    confirmed reservation has not changed yet.
                                </p>
                            )}
                        </li>
                    ))}
                </ul>
            )}
            {reservation.can_request_changes && (
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.transform((data) => ({
                            ...data,
                            idempotency_key: crypto.randomUUID(),
                        }));
                        form.post(
                            `/track-request/${request.reference}/changes`,
                            {
                                preserveScroll: true,
                            },
                        );
                    }}
                    className="mt-5 grid gap-4 sm:grid-cols-2"
                >
                    <label className="text-sm font-medium">
                        Proposed event date
                        <input
                            type="date"
                            value={form.data.event_date}
                            onChange={(event) =>
                                form.setData("event_date", event.target.value)
                            }
                            aria-invalid={Boolean(errors.event_date)}
                            className="mt-1 w-full"
                        />
                        <FieldError error={errors.event_date} />
                    </label>
                    <label className="text-sm font-medium">
                        Proposed start time
                        <input
                            type="time"
                            value={form.data.event_start_time}
                            onChange={(event) =>
                                form.setData(
                                    "event_start_time",
                                    event.target.value,
                                )
                            }
                            aria-invalid={Boolean(errors.event_start_time)}
                            className="mt-1 w-full"
                        />
                        <FieldError error={errors.event_start_time} />
                    </label>
                    <label className="text-sm font-medium sm:col-span-2">
                        Proposed venue
                        <input
                            value={form.data.venue_information}
                            onChange={(event) =>
                                form.setData(
                                    "venue_information",
                                    event.target.value,
                                )
                            }
                            aria-invalid={Boolean(errors.venue_information)}
                            className="mt-1 w-full"
                        />
                        <FieldError error={errors.venue_information} />
                    </label>
                    <label className="text-sm font-medium">
                        Proposed guest count
                        <input
                            type="number"
                            min="1"
                            value={form.data.guest_count}
                            onChange={(event) =>
                                form.setData("guest_count", event.target.value)
                            }
                            aria-invalid={Boolean(errors.guest_count)}
                            className="mt-1 w-full"
                        />
                        <FieldError error={errors.guest_count} />
                    </label>
                    <label className="text-sm font-medium sm:col-span-2">
                        What would you like to change? (optional note)
                        <textarea
                            value={form.data.request_note}
                            onChange={(event) =>
                                form.setData("request_note", event.target.value)
                            }
                            aria-invalid={Boolean(errors.request_note)}
                            className="mt-1 min-h-24 w-full"
                        />
                        <FieldError error={errors.request_note} />
                    </label>
                    {changeMenuOffering && (
                        <details className="sm:col-span-2 rounded-xl border border-stone-200 p-3">
                            <summary className="cursor-pointer font-semibold text-emerald-950">
                                Change menu selections
                            </summary>
                            <p className="mt-2 text-sm text-stone-600">
                                Keep Included Items and choose the complete
                                proposed menu. Saving validates it; submitting
                                still requires Administrator review.
                            </p>
                            <div className="mt-3">
                                <PackageConfigurator
                                    offering={changeMenuOffering.offering}
                                    previewUrl={`/catering-packages/${changeMenuOffering.offering.id}/preview`}
                                    initialQuantity={
                                        Number(form.data.guest_count) ||
                                        changeMenuOffering.quantity
                                    }
                                    initialGroups={changeMenuOffering.groups}
                                    selectionLabel="Use proposed menu"
                                    onConfigurationSaved={(configuration) =>
                                        form.setData("menu_selection", {
                                            groups: configuration.groups,
                                        })
                                    }
                                />
                            </div>
                            {form.data.menu_selection && (
                                <p className="mt-3 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-950">
                                    Proposed menu is ready to submit for review.
                                </p>
                            )}
                            <FieldError error={errors.menu_selection} />
                        </details>
                    )}
                    {changeAddonOfferings.length > 0 && (
                        <CommercialChangeAddonConfigurator
                            offerings={changeAddonOfferings}
                            selection={form.data.addon_selection}
                            onChange={(selection) =>
                                form.setData("addon_selection", selection)
                            }
                        />
                    )}
                    <FieldError error={errors.changes} />
                    <button
                        disabled={form.processing}
                        className="w-fit rounded-lg bg-emerald-800 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                    >
                        Request changes
                    </button>
                </form>
            )}
        </section>
    );
}

function RevisedQuotationAgreement({
    request,
    change,
}: {
    request: ProgressRequest;
    change: NonNullable<
        ProgressRequest["reservation"]
    >["change_requests"][number];
}) {
    const agreement = useForm({ accepted_by_name: request.customer_name });

    if (!change.quotation_version) return null;

    if (change.quotation_version.status !== "issued") {
        return (
            <p className="mt-2 text-amber-900">
                The Administrator approved this change and is preparing its
                revised quotation. Your existing confirmed reservation remains
                in effect.
            </p>
        );
    }

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                agreement.post(
                    `/track-request/${request.reference}/changes/${change.id}/agree`,
                    { preserveScroll: true },
                );
            }}
            className="mt-3 rounded-lg border border-emerald-200 bg-white p-3"
        >
            <p className="font-semibold text-emerald-950">
                Revised quotation v{change.quotation_version.version_number}:{" "}
                {formatPesos(change.quotation_version.total_minor)}
            </p>
            <p className="mt-1 text-slate-600">
                Agreeing records your acceptance of the revised commercial terms
                only. It does not move payments or change your confirmed event
                until Administrator re-confirmation succeeds.
            </p>
            <label className="mt-3 block font-medium">
                Your name to agree
                <input
                    value={agreement.data.accepted_by_name}
                    onChange={(event) =>
                        agreement.setData(
                            "accepted_by_name",
                            event.target.value,
                        )
                    }
                    className="mt-1 w-full"
                    aria-invalid={Boolean(agreement.errors.accepted_by_name)}
                />
            </label>
            <FieldError error={agreement.errors.accepted_by_name} />
            <FieldError
                error={
                    (agreement.errors as Record<string, string | undefined>)
                        .change_request ??
                    (agreement.errors as Record<string, string | undefined>)
                        .quotation
                }
            />
            <button
                disabled={agreement.processing}
                className="mt-3 rounded-lg bg-emerald-800 px-3 py-2 font-semibold text-white disabled:opacity-50"
            >
                Agree to revised quotation
            </button>
        </form>
    );
}

function QuotationCard({
    quote,
    reference,
}: {
    quote: Quote;
    reference: string;
}) {
    const payment = quote.terms_snapshot?.reservation_payment;
    return (
        <section className="mt-5 rounded-2xl border bg-white p-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 className="font-semibold">Quotation {quote.reference}</h2>
                <button
                    type="button"
                    onClick={() => window.print()}
                    className="rounded-lg border border-emerald-800 px-3 py-2 text-sm font-semibold text-emerald-900 print:hidden"
                >
                    Print or save as PDF
                </button>
            </div>
            <ul className="mt-4 space-y-2 text-sm">
                {quote.line_snapshots.map((line) => (
                    <li
                        key={line.line_number}
                        className="rounded-lg bg-stone-50 p-3"
                    >
                        {line.description ??
                            line.line_type.replaceAll("_", " ")}{" "}
                        · Quantity {line.quantity} ·{" "}
                        {formatPesos(line.total_minor)}
                    </li>
                ))}
            </ul>
            <p className="mt-4 text-xl font-semibold">
                Total: {formatPesos(quote.total_minor)}
            </p>
            {payment && (
                <p className="mt-3 text-sm text-stone-600">
                    Reservation payment:{" "}
                    {payment.type === "fixed_amount"
                        ? formatPesos(payment.amount_minor ?? "0")
                        : `${(payment.basis_points ?? 0) / 100}%`}
                    . Payment deadline begins after your first receipt
                    submission.
                </p>
            )}
            {quote.valid_until && !quote.acceptance && (
                <p className="mt-2 text-sm text-stone-600">
                    Quotation valid until{" "}
                    {formatManilaTimestamp(quote.valid_until)}.
                </p>
            )}
            {quote.acceptance && (
                <div className="mt-5 rounded-xl bg-emerald-50 p-4 text-sm">
                    <strong>
                        Quotation accepted through receipt submission
                    </strong>
                    <p className="mt-1">
                        Accepted{" "}
                        {formatManilaTimestamp(quote.acceptance.accepted_at)}.
                        Reservation payment is due{" "}
                        {formatManilaTimestamp(quote.acceptance.payment_due_at)}
                        {quote.acceptance.final_payment_due_at
                            ? `; final payment is due ${formatManilaTimestamp(quote.acceptance.final_payment_due_at)}.`
                            : "."}
                    </p>
                </div>
            )}
            {(quote.status === "issued" || quote.acceptance) && (
                <PaymentPanel quote={quote} reference={reference} />
            )}
            {!quote.acceptance && quote.status !== "issued" && (
                <p className="mt-4 text-sm text-stone-600">
                    Quotation status: {quote.status.replaceAll("_", " ")}.
                </p>
            )}
        </section>
    );
}

function PaymentPanel({
    quote,
    reference,
}: {
    quote: Quote;
    reference: string;
}) {
    const errors =
        usePage<{ errors: Record<string, string> }>().props.errors ?? {};
    const summary = quote.payment_summary;
    return (
        <section className="mt-6 border-t pt-5">
            <h3 className="font-semibold">Payments</h3>
            {summary && (
                <>
                    <div className="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                        <p>
                            Required reservation payment:{" "}
                            <strong>
                                {formatPesos(
                                    String(summary.reservation_required_minor),
                                )}
                            </strong>
                        </p>
                        <p>
                            Verified:{" "}
                            <strong>
                                {formatPesos(String(summary.verified_minor))}
                            </strong>
                        </p>
                        <p>
                            Outstanding:{" "}
                            <strong>
                                {formatPesos(String(summary.remaining_minor))}
                            </strong>
                        </p>
                        <p
                            className={
                                summary.reservation_payment_overdue ||
                                summary.final_payment_overdue
                                    ? "font-semibold text-red-700"
                                    : ""
                            }
                        >
                            {summary.reservation_payment_overdue
                                ? "Reservation payment is overdue."
                                : summary.final_payment_overdue
                                  ? "Final payment is overdue."
                                  : "Payments remain subject to verification."}
                        </p>
                    </div>
                    {summary.reservation_payment_satisfied ? (
                        <p className="mt-3 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-900">
                            Your reservation-payment requirement has been
                            verified. Reservation confirmation still requires
                            Administrator availability review.
                        </p>
                    ) : summary.verified_minor > 0 ? (
                        <p className="mt-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-950">
                            We verified your payment of{" "}
                            <strong>
                                {formatPesos(String(summary.verified_minor))}
                            </strong>
                            . An additional{" "}
                            <strong>
                                {formatPesos(
                                    String(summary.reservation_remaining_minor),
                                )}
                            </strong>{" "}
                            is required to complete your reservation payment.
                        </p>
                    ) : null}
                </>
            )}
            {quote.payments.length > 0 && (
                <ul className="mt-4 space-y-2 text-sm">
                    {quote.payments.map((payment) => (
                        <li
                            key={payment.id}
                            className="rounded bg-stone-50 p-3"
                        >
                            {payment.amount_minor ? (
                                <strong>
                                    {formatPesos(payment.amount_minor)}
                                </strong>
                            ) : (
                                <strong>Receipt uploaded</strong>
                            )}{" "}
                            {payment.method ? `via ${payment.method} — ` : ""}
                            {payment.status.replaceAll("_", " ")}
                            {payment.rejection_reason
                                ? `: ${payment.rejection_reason}`
                                : ""}
                            {payment.customer_message && (
                                <p className="mt-1 text-slate-600">
                                    {payment.customer_message}
                                </p>
                            )}
                            {["pending_verification", "rejected"].includes(
                                payment.status,
                            ) && (
                                <ReceiptReplacement
                                    reference={reference}
                                    paymentId={payment.id}
                                />
                            )}
                        </li>
                    ))}
                </ul>
            )}
            {quote.payment_upload_open ? (
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        const data = new FormData(event.currentTarget);
                        data.set("idempotency_key", crypto.randomUUID());
                        router.post(
                            `/track-request/${reference}/payments`,
                            data,
                            { forceFormData: true, preserveScroll: true },
                        );
                    }}
                    className="mt-5 rounded-xl border bg-stone-50 p-4"
                >
                    <h4 className="font-semibold">
                        {quote.acceptance
                            ? "Upload additional payment"
                            : "Upload payment receipt"}
                    </h4>
                    <p className="mt-1 text-sm text-stone-600">
                        {quote.acceptance
                            ? "Your receipt remains pending until an Administrator verifies the amount actually received."
                            : "By uploading your first receipt, you accept this displayed quotation. The receipt remains pending until an Administrator verifies the amount actually received."}
                    </p>
                    <PaymentInstructions
                        instructions={quote.payment_instructions}
                    />
                    {errors.payment && (
                        <p role="alert" className="mt-3 text-sm text-rose-700">
                            {errors.payment}
                        </p>
                    )}
                    <label
                        htmlFor="payment-proof"
                        className="mt-3 block text-sm"
                    >
                        Receipt image (JPEG, PNG, or WebP)
                    </label>
                    <input
                        id="payment-proof"
                        required
                        name="proof"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        aria-invalid={Boolean(errors.proof)}
                        className="mt-1 w-full"
                    />
                    <FieldError error={errors.proof} />
                    <button className="mt-3 rounded-lg bg-emerald-800 px-4 py-2 text-sm font-semibold text-white">
                        {quote.acceptance
                            ? "Upload additional receipt"
                            : "Accept quotation and upload receipt"}
                    </button>
                </form>
            ) : (
                <p className="mt-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900">
                    The payment-upload deadline{" "}
                    {quote.payment_upload_cutoff_at
                        ? `was ${formatManilaTimestamp(quote.payment_upload_cutoff_at)}`
                        : "has passed"}
                    . Contact ZEK Catering for assistance.
                </p>
            )}
        </section>
    );
}

function PaymentInstructions({
    instructions,
}: {
    instructions: Quote["payment_instructions"];
}) {
    return (
        <div className="mt-3 rounded-lg bg-stone-100 p-3 text-sm">
            <strong>Payment instructions</strong>
            {instructions.updated_since_issuance && (
                <p className="mt-1 font-medium text-emerald-800">
                    Payment instructions have been updated since this quotation
                    was issued. Use the current details below when preparing
                    your payment.
                </p>
            )}
            {instructions.contains_placeholders && (
                <p className="mt-1 font-medium text-amber-800">
                    Sample account information — contact ZEK Catering before
                    sending payment.
                </p>
            )}
            <dl className="mt-2 space-y-1">
                <div>
                    <dt className="inline font-medium">Account name: </dt>
                    <dd className="inline">{instructions.account_name}</dd>
                </div>
                <div>
                    <dt className="inline font-medium">E-wallet: </dt>
                    <dd className="inline">{instructions.ewallet_number}</dd>
                </div>
                <div>
                    <dt className="inline font-medium">Bank account: </dt>
                    <dd className="inline">{instructions.bank_account}</dd>
                </div>
            </dl>
            <p className="mt-2 whitespace-pre-line text-slate-600">
                {instructions.additional_instructions}
            </p>
        </div>
    );
}

function ReceiptReplacement({
    reference,
    paymentId,
}: {
    reference: string;
    paymentId: number;
}) {
    const errors =
        usePage<{ errors: Record<string, string> }>().props.errors ?? {};
    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                router.post(
                    `/track-request/${reference}/payments/${paymentId}/evidence`,
                    new FormData(event.currentTarget),
                    { forceFormData: true, preserveScroll: true },
                );
            }}
            className="mt-3 rounded border bg-white p-3"
        >
            <label className="block text-sm font-medium">
                Replace receipt image
                <input
                    required
                    name="proof"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    className="mt-1 block w-full"
                    aria-invalid={Boolean(errors.proof)}
                />
            </label>
            <FieldError error={errors.proof} />
            <button className="mt-2 rounded border border-emerald-800 px-3 py-1 text-xs font-semibold text-emerald-900">
                Replace and resubmit
            </button>
        </form>
    );
}

function FieldError({ error }: { error?: string }) {
    return error ? (
        <span role="alert" className="mt-1 block text-sm text-rose-700">
            {error}
        </span>
    ) : null;
}
