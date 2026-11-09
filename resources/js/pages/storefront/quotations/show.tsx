import { router, usePage } from "@inertiajs/react";
import { formatPesos } from "../../../features/packages/money";
import StorefrontLayout from "../../../layouts/storefront-layout";

type Quote = {
    reference: string;
    valid_until: string | null;
    status?: string;
    customer_name?: string;
    event_date?: string | null;
    event_start_time?: string | null;
    venue_information?: string | null;
    line_snapshots?: Array<{
        line_number: number;
        line_type: string;
        description?: string;
        quantity: number;
        total_minor: string;
    }>;
    subtotal_minor?: string;
    adjustment_minor?: string;
    total_minor?: string;
    terms_snapshot?: {
        tax_price_treatment_statement?: string;
        reservation_payment?: {
            type: string;
            amount_minor: string | null;
            basis_points: number | null;
            payment_deadline_hours: number;
        };
        menu_finalization?: { days_after_acceptance: number; terms: string };
        cancellation_refund_terms?: string;
        rescheduling_terms?: string;
        customer_conditions?: string;
    } | null;
    acceptance?: {
        method: string;
        accepted_by_name: string;
        accepted_at: string;
        payment_due_at: string;
        menu_finalization_due_at: string;
    } | null;
};

export default function PublicQuotationShow({
    token,
    verified,
    contactHint,
    quotation,
    testDriverAvailable,
}: {
    token: string;
    verified: boolean;
    contactHint: string | null;
    quotation: Quote;
    testDriverAvailable: boolean;
}) {
    const testCode = usePage<{ flash: { quotation_test_otp?: string } }>().props
        .flash?.quotation_test_otp;
    if (!verified) {
        return (
            <StorefrontLayout title="Verify your quotation">
                <div className="mx-auto max-w-xl px-4 py-16">
                    <p className="text-xs font-bold uppercase tracking-[.16em] text-amber-700">
                        Secure quotation
                    </p>
                    <h1 className="mt-3 font-serif text-4xl font-bold text-emerald-950">
                        Verify to view {quotation.reference}
                    </h1>
                    <p className="mt-4 text-stone-600">
                        For your privacy, verify the mobile number associated
                        with this quotation before viewing its details.
                    </p>
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            router.post(`/quotes/${token}/verification`, {
                                test_driver: testDriverAvailable,
                            });
                        }}
                        className="mt-6"
                    >
                        <button className="rounded-xl bg-emerald-800 px-5 py-3 text-sm font-semibold text-white">
                            {testDriverAvailable
                                ? "Generate test verification code"
                                : "Request SMS verification code"}
                        </button>
                    </form>
                    {testCode && (
                        <p className="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950">
                            Development/test code: <strong>{testCode}</strong>.
                            It is available only outside production.
                        </p>
                    )}
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            router.post(
                                `/quotes/${token}/verification/confirm`,
                                {
                                    code: new FormData(event.currentTarget).get(
                                        "code",
                                    ),
                                },
                            );
                        }}
                        className="mt-6 space-y-3"
                    >
                        <label className="block text-sm font-medium">
                            Six-digit verification code
                            <input
                                required
                                name="code"
                                inputMode="numeric"
                                pattern="[0-9]{6}"
                                className="mt-1 w-full"
                            />
                        </label>
                        <button className="rounded-xl border border-emerald-800 px-5 py-3 text-sm font-semibold text-emerald-900">
                            Verify quotation
                        </button>
                    </form>
                </div>
            </StorefrontLayout>
        );
    }
    const terms = quotation.terms_snapshot;
    return (
        <StorefrontLayout title={`Quotation ${quotation.reference}`}>
            <div className="mx-auto max-w-3xl px-4 py-12 sm:px-6">
                <p className="text-xs font-bold uppercase tracking-[.16em] text-amber-700">
                    Verified quotation
                </p>
                <h1 className="mt-3 font-serif text-4xl font-bold text-emerald-950">
                    {quotation.reference}
                </h1>
                <p className="mt-3 text-sm text-stone-600">
                    Verified for {contactHint ?? "your mobile number"}. Access
                    expires after 30 minutes of verification.
                </p>
                <button
                    type="button"
                    onClick={() => window.print()}
                    className="mt-4 rounded-xl border border-emerald-800 px-4 py-2 text-sm font-semibold text-emerald-900 print:hidden"
                >
                    Print or save as PDF
                </button>
                <section className="mt-8 rounded-2xl border bg-white p-5">
                    <h2 className="font-semibold">Event details</h2>
                    <p className="mt-3 text-sm">
                        {quotation.customer_name} ·{" "}
                        {quotation.event_date ?? "Event date to be confirmed"}{" "}
                        {quotation.event_start_time ?? ""}
                    </p>
                    <p className="mt-1 text-sm text-stone-600">
                        {quotation.venue_information ?? "Venue to be confirmed"}
                    </p>
                </section>
                <section className="mt-5 rounded-2xl border bg-white p-5">
                    <h2 className="font-semibold">Your quoted selections</h2>
                    <ul className="mt-3 space-y-2 text-sm">
                        {quotation.line_snapshots?.map((line) => (
                            <li
                                key={line.line_number}
                                className="rounded-lg bg-stone-50 p-3"
                            >
                                {line.description ??
                                    line.line_type.replaceAll("_", " ")}{" "}
                                · quantity {line.quantity} ·{" "}
                                {formatPesos(line.total_minor)}
                            </li>
                        ))}
                    </ul>
                    <p className="mt-4 text-lg font-semibold">
                        Total: {formatPesos(quotation.total_minor ?? "0")}
                    </p>
                </section>
                {terms && (
                    <section className="mt-5 rounded-2xl border bg-white p-5 text-sm">
                        <h2 className="font-semibold">Commercial terms</h2>
                        <div className="mt-3 space-y-3">
                            <p>{terms.tax_price_treatment_statement}</p>
                            <p>
                                Reservation payment:{" "}
                                {terms.reservation_payment?.type ===
                                "fixed_amount"
                                    ? formatPesos(
                                          terms.reservation_payment
                                              .amount_minor ?? "0",
                                      )
                                    : `${(terms.reservation_payment?.basis_points ?? 0) / 100}%`}{" "}
                                due within{" "}
                                {
                                    terms.reservation_payment
                                        ?.payment_deadline_hours
                                }{" "}
                                hours after acceptance.
                            </p>
                            <p>
                                Menu finalization:{" "}
                                {terms.menu_finalization?.terms} Deadline:{" "}
                                {terms.menu_finalization?.days_after_acceptance}{" "}
                                days after acceptance.
                            </p>
                            <p>{terms.cancellation_refund_terms}</p>
                            <p>{terms.rescheduling_terms}</p>
                            <p>{terms.customer_conditions}</p>
                        </div>
                    </section>
                )}
                {quotation.acceptance ? (
                    <section className="mt-5 rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                        <h2 className="font-semibold">Quotation accepted</h2>
                        <p className="mt-2 text-sm">
                            Accepted by {quotation.acceptance.accepted_by_name}.
                            Payment is due by{" "}
                            {quotation.acceptance.payment_due_at}; menu
                            finalization is due by{" "}
                            {quotation.acceptance.menu_finalization_due_at}.
                            Payment verification and reservation confirmation
                            remain separate steps.
                        </p>
                    </section>
                ) : (
                    <section className="mt-5 rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                        <h2 className="font-semibold">
                            Continue through Track Your Request
                        </h2>
                        <p className="mt-2 text-sm text-emerald-950">
                            Upload a receipt through Track Your Request to
                            accept this quotation and begin Administrator
                            payment review. Receipt submission does not verify
                            payment or confirm a reservation.
                        </p>
                    </section>
                )}
            </div>
        </StorefrontLayout>
    );
}
