<?php

namespace App\Actions\Reservations;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\ReservationStatusEvent;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\BusinessPolicies\CurrentBusinessPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompleteReservation
{
    public function __construct(
        private AuditLogger $audit,
        private CurrentBusinessPolicy $policies,
    ) {}

    public function handle(User $actor, Reservation $reservation, ?string $note): Reservation
    {
        if (! $actor->active || ! $actor->isAdmin()) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($actor, $reservation, $note): Reservation {
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            if ($reservation->status !== ReservationStatus::Confirmed) {
                throw ValidationException::withMessages(['reservation' => 'Only a confirmed reservation can be marked completed.']);
            }
            if ($reservation->event_start_time === null) {
                throw ValidationException::withMessages(['reservation' => 'Record the scheduled event start time before marking this reservation completed.']);
            }

            $scheduledEnd = CarbonImmutable::createFromFormat(
                'Y-m-d H:i:s',
                $reservation->event_date->toDateString().' '.$reservation->event_start_time,
                'Asia/Manila',
            )->addHours($this->policies->current()->event_duration_hours);
            if (now('Asia/Manila')->lessThan($scheduledEnd)) {
                throw ValidationException::withMessages([
                    'reservation' => 'This reservation can be marked completed only after its scheduled event end time ('.$scheduledEnd->format('M j, Y g:i A T').').',
                ]);
            }

            $reservation->forceFill([
                'status' => ReservationStatus::Completed,
                'completed_by' => $actor->id,
                'completed_at' => now(),
                'completion_note' => filled($note) ? trim($note) : null,
            ])->save();
            ReservationStatusEvent::create([
                'reservation_id' => $reservation->id,
                'from_status' => ReservationStatus::Confirmed,
                'to_status' => ReservationStatus::Completed,
                'note' => filled($note) ? trim($note) : null,
                'changed_by' => $actor->id,
            ]);
            $this->audit->record('reservation.completed', $actor, $reservation, ['status' => 'confirmed'], ['status' => 'completed']);

            return $reservation->fresh(['completer']);
        });
    }
}
