<?php

namespace App\Actions\Reservations;

use App\Enums\ReservationChangeRequestStatus;
use App\Enums\ReservationStatus;
use App\Models\BusinessPolicy;
use App\Models\ReservationChangeRequest;
use App\Models\ReservationChangeRequestEvent;
use App\Models\ReservationConfirmationEvent;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Payments\PaymentBalance;
use App\Services\Reservations\ReservationCapacity;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApplyReservationChangeRequest
{
    public function __construct(private PaymentBalance $balances, private ReservationCapacity $capacity, private AuditLogger $audit) {}

    public function handle(User $actor, ReservationChangeRequest $attempt): ReservationChangeRequest
    {
        if (! $actor->active || ! $actor->isAdmin()) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($actor, $attempt): ReservationChangeRequest {
            $change = ReservationChangeRequest::query()->with(['reservation', 'quotation.versions.acceptance', 'quotationVersion'])->lockForUpdate()->findOrFail($attempt->id);
            if ($change->status !== ReservationChangeRequestStatus::AwaitingReconfirmation) {
                throw ValidationException::withMessages(['change_request' => 'This request must be approved and, when commercial terms changed, agreed by the customer before re-confirmation.']);
            }
            $reservation = $change->reservation()->lockForUpdate()->firstOrFail();
            if ($reservation->status !== ReservationStatus::Confirmed) {
                throw ValidationException::withMessages(['reservation' => 'Only an active confirmed reservation can receive this change.']);
            }
            $version = $change->quotation_version_id !== null ? $change->quotationVersion : $reservation->confirmedQuotationVersion;
            if ($version === null || $version->acceptance === null) {
                throw ValidationException::withMessages(['quotation' => 'An accepted quotation Version is required before applying this change.']);
            }
            $balance = $this->balances->forVersion($version);
            if (! $balance['reservation_payment_satisfied']) {
                throw ValidationException::withMessages(['payment' => 'Reallocate verified payments or record additional payment before re-confirming this change.']);
            }
            $policy = BusinessPolicy::query()->where('key', 'current')->lockForUpdate()->sole();
            $proposed = $change->proposed_snapshot;
            $newDate = CarbonImmutable::parse($proposed['event_date'], 'Asia/Manila');
            $capacity = $this->capacity->assessMoveLocked($reservation->event_date->toImmutable(), $newDate, $policy, $proposed['guest_count'], $reservation->id);
            $before = ['event_date' => $reservation->event_date->toDateString(), 'event_start_time' => $reservation->event_start_time, 'venue_information' => $reservation->venue_information, 'guest_count' => $reservation->guest_count, 'confirmed_quotation_version_id' => $reservation->confirmed_quotation_version_id];
            $snapshot = [
                'schema' => 2, 'reservation_change_request_id' => $change->id,
                'quotation_id' => $change->quotation_id, 'quotation_version_id' => $version->id, 'quotation_version_number' => $version->version_number,
                'event_date' => $newDate->toDateString(), 'event_start_time' => $proposed['event_start_time'], 'venue_information' => $proposed['venue_information'],
                'guest_count' => $proposed['guest_count'], 'reconfirmed_at' => now()->toIso8601String(),
            ];
            $reservation->forceFill([
                'confirmed_quotation_version_id' => $version->id,
                'event_date' => $newDate->toDateString(), 'event_start_time' => $proposed['event_start_time'],
                'venue_information' => $proposed['venue_information'], 'guest_count' => $proposed['guest_count'],
                'confirmation_snapshot' => $snapshot, 'capacity_snapshot' => $capacity,
                'confirmed_by' => $actor->id, 'confirmed_at' => now(),
            ])->save();
            ReservationConfirmationEvent::create([
                'quotation_id' => $change->quotation_id, 'quotation_version_id' => $version->id, 'reservation_id' => $reservation->id,
                'outcome' => 'confirmed', 'payment_snapshot' => ['quotation_version_id' => $version->id, 'reservation_payment_satisfied' => true, 'verified_minor' => $balance['verified_minor'], 'remaining_minor' => $balance['remaining_minor']],
                'capacity_snapshot' => $capacity, 'recorded_by' => $actor->id,
            ]);
            $change->forceFill(['status' => ReservationChangeRequestStatus::Applied, 'applied_by' => $actor->id, 'applied_at' => now()])->save();
            ReservationChangeRequestEvent::create([
                'reservation_change_request_id' => $change->id,
                'from_status' => ReservationChangeRequestStatus::AwaitingReconfirmation->value,
                'to_status' => ReservationChangeRequestStatus::Applied->value,
                'note' => 'Reservation change applied after capacity and payment re-confirmation.', 'actor_id' => $actor->id,
            ]);
            $this->audit->record('reservation.change_applied', $actor, $reservation, $before, ['reservation_change_request_id' => $change->id, ...$snapshot]);

            return $change->fresh(['reservation', 'quotationVersion']);
        });
    }
}
