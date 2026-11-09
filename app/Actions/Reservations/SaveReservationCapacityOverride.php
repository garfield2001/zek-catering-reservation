<?php

namespace App\Actions\Reservations;

use App\Models\ReservationCapacityOverride;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class SaveReservationCapacityOverride
{
    public function __construct(private AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, array $data): ReservationCapacityOverride
    {
        if (! $actor->active || ! $actor->isAdmin()) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($actor, $data): ReservationCapacityOverride {
            $date = $data['event_date'];
            DB::selectOne('SELECT pg_advisory_xact_lock(hashtext(?))', ["reservation-capacity:{$date}"]);
            $override = ReservationCapacityOverride::query()->whereDate('event_date', $date)->lockForUpdate()->first();
            $before = $override?->only(['event_date', 'maximum_confirmed_events', 'maximum_guests']);
            $override ??= new ReservationCapacityOverride(['event_date' => $date]);
            $override->forceFill([
                'maximum_confirmed_events' => $data['maximum_confirmed_events'],
                'maximum_guests' => $data['maximum_guests'] ?? null,
                'updated_by' => $actor->id,
            ])->save();
            $this->audit->record('reservation.capacity_override_saved', $actor, $override, $before, $override->only(['event_date', 'maximum_confirmed_events', 'maximum_guests']));

            return $override->refresh();
        });
    }
}
