<?php

namespace App\Queries\Dashboard;

use App\Enums\CancellationRequestStatus;
use App\Enums\PaymentStatus;
use App\Enums\QuotationStatus;
use App\Enums\ReservationRequestStatus;
use App\Enums\ReservationStatus;
use App\Models\CancellationRequest;
use App\Models\Payment;
use App\Models\PencilBooking;
use App\Models\Quotation;
use App\Models\Reservation;
use App\Models\ReservationRequest;
use App\Models\User;

class OperationsDashboard
{
    // @return array{cards: list<array{label: string, count: int, href: string, description: string, tone: 'neutral'|'attention'|'success'}>, upcomingReservations: list<array{id: int, reference: string, customerName: string, eventDate: string, eventStartTime: string|null, guestCount: int|null, status: string}>}
    public function for(User $user): array
    {
        $cards = [];

        if ($user->can('viewAny', ReservationRequest::class)) {
            $cards[] = [
                'label' => 'Requests to review',
                'count' => ReservationRequest::query()
                    ->whereIn('status', [
                        ReservationRequestStatus::Submitted->value,
                        ReservationRequestStatus::UnderReview->value,
                        ReservationRequestStatus::NeedsInformation->value,
                    ])->count(),
                'href' => '/admin/reservation-requests',
                'description' => 'Customer and internal requests that are still open.',
                'tone' => 'attention',
            ];
        }

        if ($user->can('viewAny', Quotation::class)) {
            $cards[] = [
                'label' => 'Quotation drafts',
                'count' => Quotation::query()->where('status', QuotationStatus::Draft->value)->count(),
                'href' => '/admin/quotations?status=draft',
                'description' => 'Drafts that have not been issued to a customer.',
                'tone' => 'neutral',
            ];
        }

        if ($user->can('viewAny', Payment::class)) {
            $cards[] = [
                'label' => 'Payments to verify',
                'count' => Payment::query()->where('status', PaymentStatus::PendingVerification->value)->count(),
                'href' => '/admin/payments',
                'description' => 'Submitted proof and recorded receipts awaiting Administrator review.',
                'tone' => 'attention',
            ];
        }

        if ($user->can('viewAny', Reservation::class)) {
            $cards[] = [
                'label' => 'Accepted quotations to review',
                'count' => Quotation::query()
                    ->whereHas('versions.acceptance')
                    ->whereDoesntHave('reservation')
                    ->count(),
                'href' => '/admin/reservations',
                'description' => 'Acceptance does not reserve capacity; review payment readiness and availability.',
                'tone' => 'attention',
            ];
        }

        if ($user->can('viewAny', CancellationRequest::class)) {
            $cards[] = [
                'label' => 'Cancellation and refund cases',
                'count' => CancellationRequest::query()
                    ->whereIn('status', [
                        CancellationRequestStatus::PendingReview->value,
                        CancellationRequestStatus::RefundUnderReview->value,
                        CancellationRequestStatus::RefundApproved->value,
                    ])->count(),
                'href' => '/admin/cancellations',
                'description' => 'Open cases requiring review or an offline refund outcome.',
                'tone' => 'attention',
            ];
        }

        if ($user->can('viewAny', PencilBooking::class)) {
            $cards[] = [
                'label' => 'Active pencil bookings',
                'count' => PencilBooking::query()->where('status', 'active')->count(),
                'href' => '/admin/pencil-bookings',
                'description' => 'Non-binding prospects and follow-ups. They never hold capacity.',
                'tone' => 'neutral',
            ];
        }

        $upcomingReservations = $user->can('viewAny', Reservation::class)
            ? Reservation::query()
                ->with('quotation:id,reference,customer_name')
                ->whereIn('status', [
                    ReservationStatus::Confirmed->value,
                    ReservationStatus::Completed->value,
                ])
                ->whereDate('event_date', '>=', now('Asia/Manila')->toDateString())
                ->orderBy('event_date')
                ->orderBy('event_start_time')
                ->limit(6)
                ->get()
                ->map(fn (Reservation $reservation): array => [
                    'id' => $reservation->id,
                    'reference' => $reservation->reference,
                    'customerName' => $reservation->quotation->customer_name,
                    'eventDate' => $reservation->event_date->toDateString(),
                    'eventStartTime' => $reservation->event_start_time,
                    'guestCount' => $reservation->guest_count,
                    'status' => $reservation->status->value,
                ])->values()->all()
            : [];

        return ['cards' => $cards, 'upcomingReservations' => $upcomingReservations];
    }
}
