<?php

namespace App\Actions\PencilBookings;

use App\Models\PencilBooking;
use App\Models\PencilBookingEvent;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClosePencilBooking
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(User $actor, PencilBooking $attempt, string $status, string $reason): PencilBooking
    {
        return DB::transaction(function () use ($actor, $attempt, $status, $reason): PencilBooking {
            $booking = PencilBooking::query()->lockForUpdate()->findOrFail($attempt->id);
            if (! in_array($status, ['closed', 'did_not_proceed'], true) || $booking->status !== 'active') {
                throw ValidationException::withMessages(['status' => 'Only an active pencil booking can be closed or marked Did Not Proceed.']);
            }
            if (! filled($reason)) {
                throw ValidationException::withMessages(['reason' => 'Record the outcome reason.']);
            }
            $booking->forceFill(['status' => $status, 'updated_by' => $actor->id])->save();
            PencilBookingEvent::create(['pencil_booking_id' => $booking->id, 'event_type' => $status, 'details' => ['reason' => trim($reason)], 'actor_id' => $actor->id]);
            $this->audit->record("pencil_booking.{$status}", $actor, $booking, ['status' => 'active'], ['status' => $status, 'reason' => trim($reason)]);

            return $booking;
        });
    }
}
