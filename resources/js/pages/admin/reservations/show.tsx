import { Link } from "@inertiajs/react";
import { CaseWorkflow } from "../../../features/reservations/components/case-workflow";
import {
    CapacityAvailabilityCard,
    PaymentReadinessCard,
    ReservationEventSummary,
    ReservationLifecycleHistory,
} from "../../../features/reservations/components/reservation-review-overview";
import {
    CompletionForm,
    ConfirmationActions,
    ReservationStatus,
} from "../../../features/reservations/components/reservation-decision-actions";
import {
    CancellationManagement,
    type CancellationRecord,
} from "../../../features/cancellations/components/cancellation-management";
import { type ChangeAddonOffering } from "../../../features/reservations/components/commercial-change-configurator";
import {
    ReservationChangeRequests,
    type ReservationChangeRequest,
} from "../../../features/reservations/components/reservation-change-requests";
import type { PublicPackage } from "../../../features/storefront/types";
import AdminLayout from "../../../layouts/admin-layout";

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

    return (
        <AdminLayout
            title={`Review ${quotation.reference}`}
            description="Confirmation is an Administrator decision. Accepted quotations and verified payments do not reserve capacity until this review is completed."
            section="reservations"
        >
            <CaseWorkflow
                steps={[
                    {
                        label: "Inquiry",
                        detail:
                            quotation.origin === "manual"
                                ? "Manual quotation"
                                : "Inquiry details are retained with the quotation.",
                        state: "complete",
                    },
                    {
                        label: "Quotation",
                        detail: quotation.latest_accepted_version
                            ? `Accepted version ${quotation.latest_accepted_version.version_number}`
                            : "An accepted quotation is required.",
                        state: quotation.latest_accepted_version
                            ? "complete"
                            : "blocked",
                        href: `/admin/quotations/${quotation.id}`,
                    },
                    {
                        label: "Payment",
                        detail: paymentReadiness?.reservation_payment_satisfied
                            ? "Reservation payment requirement satisfied."
                            : "Verified reservation payment is required.",
                        state: paymentReadiness?.reservation_payment_satisfied
                            ? "complete"
                            : "blocked",
                        href: `/admin/quotations/${quotation.id}`,
                    },
                    {
                        label: "Reservation",
                        detail: reservation
                            ? reservation.status.replaceAll("_", " ")
                            : canConfirm
                              ? "Ready for Administrator confirmation."
                              : "Review payment and capacity blockers below.",
                        state: reservation
                            ? "complete"
                            : canConfirm
                              ? "current"
                              : "blocked",
                    },
                ]}
            />
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
                <ReservationEventSummary quotation={quotation} />
                <PaymentReadinessCard readiness={paymentReadiness} />
            </div>

            <CapacityAvailabilityCard capacity={capacity} />

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

            <ReservationLifecycleHistory
                decisions={confirmationEvents}
                reservation={reservation}
            />
        </AdminLayout>
    );
}
