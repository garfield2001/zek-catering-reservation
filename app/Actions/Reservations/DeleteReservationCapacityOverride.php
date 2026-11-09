<?php

namespace App\Actions\Reservations;

use App\Models\ReservationCapacityOverride;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class DeleteReservationCapacityOverride
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(User $actor, ReservationCapacityOverride $override): void
    {
        if (! $actor->active || ! $actor->isAdmin()) {
            throw new AuthorizationException;
        }

        DB::transaction(function () use ($actor, $override): void {
            DB::selectOne('SELECT pg_advisory_xact_lock(hashtext(?))', ["reservation-capacity:{$override->event_date->toDateString()}"]);
            $override = ReservationCapacityOverride::query()->lockForUpdate()->findOrFail($override->id);
            $before = $override->only(['event_date', 'maximum_confirmed_events', 'maximum_guests']);
            $this->audit->record('reservation.capacity_override_removed', $actor, $override, $before, null);
            $override->delete();
        });
    }
}
