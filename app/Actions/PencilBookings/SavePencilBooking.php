<?php

namespace App\Actions\PencilBookings;

use App\Models\PencilBooking;
use App\Models\PencilBookingEvent;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SavePencilBooking
{
    public function __construct(private AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function create(User $actor, array $data): PencilBooking
    {
        return DB::transaction(function () use ($actor, $data): PencilBooking {
            $existing = PencilBooking::query()->where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }
            $booking = PencilBooking::create([...$this->fields($data), 'idempotency_key' => $data['idempotency_key'], 'status' => 'active', 'created_by' => $actor->id, 'updated_by' => $actor->id]);
            $this->event($booking, 'created', $actor);
            $this->audit->record('pencil_booking.created', $actor, $booking, null, ['status' => 'active', 'event_date' => $booking->getRawOriginal('event_date')]);

            return $booking;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(User $actor, PencilBooking $attempt, array $data): PencilBooking
    {
        return DB::transaction(function () use ($actor, $attempt, $data): PencilBooking {
            $booking = PencilBooking::query()->lockForUpdate()->findOrFail($attempt->id);
            if ($booking->status !== 'active') {
                throw ValidationException::withMessages(['booking' => 'Only an active pencil booking can be edited.']);
            }
            $fields = $this->fields($data);
            $changed = array_keys(array_filter($fields, fn (mixed $value, string $field): bool => $this->currentValue($booking, $field) != $value, ARRAY_FILTER_USE_BOTH));
            $booking->forceFill([...$fields, 'updated_by' => $actor->id])->save();
            $history = ['changed_fields' => $changed];
            $this->event($booking, 'updated', $actor, $history);
            $this->audit->record('pencil_booking.updated', $actor, $booking, $history, $history);

            return $booking;
        });
    }

    /** @param array<string, mixed> $data */
    private function fields(array $data): array
    {
        return collect(['customer_name', 'customer_mobile', 'customer_email', 'event_date', 'event_start_time', 'venue_information', 'guest_count', 'notes', 'follow_up_on'])->mapWithKeys(fn ($key) => [$key => filled($data[$key] ?? null) ? (is_string($data[$key]) ? trim($data[$key]) : $data[$key]) : null])->all();
    }

    private function currentValue(PencilBooking $booking, string $field): mixed
    {
        $value = in_array($field, ['event_date', 'event_start_time', 'follow_up_on'], true) ? $booking->getRawOriginal($field) : $booking->getAttribute($field);

        return $field === 'event_start_time' && is_string($value) ? substr($value, 0, 5) : $value;
    }

    private function event(PencilBooking $booking, string $type, User $actor, ?array $details = null): void
    {
        PencilBookingEvent::create(['pencil_booking_id' => $booking->id, 'event_type' => $type, 'details' => $details, 'actor_id' => $actor->id]);
    }
}
