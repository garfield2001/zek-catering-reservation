<?php

namespace App\Actions\Reservations;

use App\Enums\ReservationStatus;
use App\Models\BusinessPolicy;
use App\Models\Quotation;
use App\Models\QuotationVersion;
use App\Models\Reservation;
use App\Models\ReservationConfirmationEvent;
use App\Models\ReservationStatusEvent;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Payments\PaymentBalance;
use App\Services\Reservations\ReservationCapacity;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ConfirmReservation
{
    public function __construct(
        private PaymentBalance $balances,
        private ReservationCapacity $capacity,
        private AuditLogger $audit,
    ) {}

    public function handle(User $actor, Quotation $quotation, ?int $manualGuestCount = null): Reservation
    {
        if (! $actor->active || ! $actor->isAdmin()) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($actor, $quotation, $manualGuestCount): Reservation {
            $quotation = Quotation::query()
                ->with(['reservationRequest', 'reservation'])
                ->lockForUpdate()
                ->findOrFail($quotation->id);
            $version = $this->latestAcceptedVersion($quotation);
            $reservation = Reservation::query()->where('quotation_id', $quotation->id)->lockForUpdate()->first();

            if ($reservation?->status !== null && $reservation->status !== ReservationStatus::Confirmed) {
                throw ValidationException::withMessages(['reservation' => 'A completed or cancelled reservation cannot be re-confirmed. Start a new authorized booking workflow instead.']);
            }
            if ($reservation?->confirmed_quotation_version_id === $version->id) {
                throw ValidationException::withMessages(['reservation' => 'This accepted quotation Version is already the confirmed reservation.']);
            }
            if ($quotation->event_date === null) {
                throw ValidationException::withMessages(['event_date' => 'Set an event date on the quotation before confirming a reservation.']);
            }

            $payment = $this->balances->forVersion($version);
            if (! $payment['reservation_payment_satisfied']) {
                throw ValidationException::withMessages(['payment' => 'The verified reservation-payment requirement must be satisfied before confirmation.']);
            }

            $policy = BusinessPolicy::query()->where('key', 'current')->lockForUpdate()->sole();
            $guestCount = $quotation->reservation_request_id !== null
                ? $quotation->reservationRequest->guest_count
                : ($manualGuestCount ?? ($reservation !== null ? $reservation->guest_count : null));
            $capacity = $this->capacity->assessLocked($quotation->event_date->toImmutable(), $policy, $guestCount, $reservation?->id);
            $confirmation = [
                'schema' => 1,
                'quotation_id' => $quotation->id,
                'quotation_reference' => $quotation->reference,
                'quotation_version_id' => $version->id,
                'quotation_version_number' => $version->version_number,
                'accepted_at' => $version->acceptance?->accepted_at?->toIso8601String(),
                'event_date' => $quotation->event_date->toDateString(),
                'event_start_time' => $quotation->event_start_time,
                'venue_information' => $quotation->venue_information,
                'guest_count' => $guestCount,
                'confirmed_at' => now()->toIso8601String(),
            ];

            if ($reservation === null) {
                $reservation = Reservation::create([
                    'reference' => $this->reference(),
                    'quotation_id' => $quotation->id,
                    'confirmed_quotation_version_id' => $version->id,
                    'status' => ReservationStatus::Confirmed,
                    'event_date' => $quotation->event_date,
                    'event_start_time' => $quotation->event_start_time,
                    'timezone' => 'Asia/Manila',
                    'venue_information' => $quotation->venue_information,
                    'guest_count' => $guestCount,
                    'confirmation_snapshot' => $confirmation,
                    'capacity_snapshot' => $capacity,
                    'confirmed_by' => $actor->id,
                    'confirmed_at' => now(),
                ]);
                ReservationStatusEvent::create([
                    'reservation_id' => $reservation->id,
                    'from_status' => null,
                    'to_status' => ReservationStatus::Confirmed,
                    'note' => 'Reservation confirmed after payment and capacity review.',
                    'changed_by' => $actor->id,
                ]);
                $auditAction = 'reservation.confirmed';
                $before = null;
            } else {
                $before = ['confirmed_quotation_version_id' => $reservation->confirmed_quotation_version_id];
                $reservation->forceFill([
                    'confirmed_quotation_version_id' => $version->id,
                    'event_date' => $quotation->event_date,
                    'event_start_time' => $quotation->event_start_time,
                    'venue_information' => $quotation->venue_information,
                    'guest_count' => $guestCount,
                    'confirmation_snapshot' => $confirmation,
                    'capacity_snapshot' => $capacity,
                    'confirmed_by' => $actor->id,
                    'confirmed_at' => now(),
                ])->save();
                $auditAction = 'reservation.reconfirmed';
            }

            ReservationConfirmationEvent::create([
                'quotation_id' => $quotation->id,
                'quotation_version_id' => $version->id,
                'reservation_id' => $reservation->id,
                'outcome' => 'confirmed',
                'payment_snapshot' => $this->paymentSnapshot($payment),
                'capacity_snapshot' => $capacity,
                'recorded_by' => $actor->id,
            ]);
            $this->audit->record($auditAction, $actor, $reservation, $before, [
                'quotation_id' => $quotation->id,
                'quotation_version_id' => $version->id,
                'event_date' => $quotation->event_date->toDateString(),
                'capacity_source' => $capacity['capacity_source'],
            ]);

            return $reservation->fresh(['confirmedQuotationVersion', 'confirmer']);
        });
    }

    private function latestAcceptedVersion(Quotation $quotation): QuotationVersion
    {
        $version = $quotation->versions()->with('acceptance')->whereHas('acceptance')->reorder('version_number', 'desc')->lockForUpdate()->first();
        if ($version === null || $version->acceptance === null) {
            throw ValidationException::withMessages(['quotation' => 'An accepted quotation Version is required before a reservation can be confirmed.']);
        }

        return $version;
    }

    /** @param array<string, int|bool|string|null> $payment @return array<string, int|bool|string|null> */
    private function paymentSnapshot(array $payment): array
    {
        return [
            'quotation_version_id' => $payment['quotation_version_id'],
            'reservation_required_minor' => $payment['reservation_required_minor'],
            'verified_minor' => $payment['verified_minor'],
            'reservation_remaining_minor' => $payment['reservation_remaining_minor'],
            'reservation_payment_satisfied' => $payment['reservation_payment_satisfied'],
            'reservation_payment_overdue' => $payment['reservation_payment_overdue'],
            'remaining_minor' => $payment['remaining_minor'],
            'unresolved_financial_obligations' => ((int) $payment['remaining_minor']) > 0 || ((int) $payment['verified_minor']) > 0,
        ];
    }

    private function reference(): string
    {
        do {
            $reference = 'RSV-'.Str::upper(Str::random(12));
        } while (Reservation::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
