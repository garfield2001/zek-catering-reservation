<?php

namespace App\Services\Reservations;

use App\Enums\ReservationStatus;
use App\Models\BusinessPolicy;
use App\Models\Reservation;
use App\Models\ReservationCapacityOverride;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationCapacity
{
    /**
     * Serializes confirmation decisions for a Manila event date and returns
     * the capacity facts used by the caller's immutable decision snapshot.
     *
     * @return array<string, int|string|null>
     */
    public function assessLocked(
        CarbonImmutable $eventDate,
        BusinessPolicy $policy,
        ?int $guestCount,
        ?int $excludingReservationId = null,
    ): array {
        $date = $eventDate->toDateString();
        DB::selectOne('SELECT pg_advisory_xact_lock(hashtext(?))', ["reservation-capacity:{$date}"]);

        $override = ReservationCapacityOverride::query()
            ->whereDate('event_date', $date)
            ->lockForUpdate()
            ->first();
        $eventLimit = $override !== null ? $override->maximum_confirmed_events : $policy->daily_confirmed_event_capacity;
        $guestLimit = $override !== null ? $override->maximum_guests : $policy->daily_guest_capacity;
        if ($guestLimit !== null && $guestCount === null) {
            throw ValidationException::withMessages([
                'guest_count' => 'Enter the expected guest count before confirming because a daily guest capacity applies to this event date.',
            ]);
        }

        $usage = Reservation::query()
            ->whereIn('status', [ReservationStatus::Confirmed->value, ReservationStatus::Completed->value])
            ->whereDate('event_date', $date)
            ->when($excludingReservationId !== null, fn ($query) => $query->whereKeyNot($excludingReservationId));
        $eventsUsed = $usage->count();
        $guestsUsed = (int) $usage->sum('guest_count');

        if ($eventsUsed >= $eventLimit) {
            throw ValidationException::withMessages([
                'availability' => "Daily confirmation capacity is full for {$date}. No reservation was confirmed.",
            ]);
        }
        if ($guestLimit !== null && $guestsUsed + (int) $guestCount > $guestLimit) {
            throw ValidationException::withMessages([
                'availability' => "Daily guest capacity is full for {$date}. No reservation was confirmed.",
            ]);
        }

        return [
            'event_date' => $date,
            'capacity_source' => $override ? 'date_override' : 'business_policy',
            'capacity_override_id' => $override?->id,
            'maximum_confirmed_events' => $eventLimit,
            'maximum_guests' => $guestLimit,
            'confirmed_events_before' => $eventsUsed,
            'confirmed_guests_before' => $guestsUsed,
            'confirmed_events_after' => $eventsUsed + 1,
            'confirmed_guests_after' => $guestsUsed + ($guestCount ?? 0),
        ];
    }

    /**
     * Locks both dates in a stable order before moving an existing confirmed
     * reservation, so a concurrent confirmation cannot consume either side
     * of the capacity swap halfway through the transaction.
     *
     * @return array<string, int|string|null>
     */
    public function assessMoveLocked(CarbonImmutable $from, CarbonImmutable $to, BusinessPolicy $policy, ?int $guestCount, int $reservationId): array
    {
        $dates = [$from->toDateString(), $to->toDateString()];
        sort($dates);
        foreach ($dates as $date) {
            DB::selectOne('SELECT pg_advisory_xact_lock(hashtext(?))', ["reservation-capacity:{$date}"]);
        }

        return $this->assessLocked($to, $policy, $guestCount, $reservationId);
    }
}
