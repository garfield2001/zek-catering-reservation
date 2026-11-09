<?php

namespace App\Actions\PencilBookings;

use App\Actions\ReservationRequest\CreateInternalReservationRequest;
use App\Models\PencilBooking;
use App\Models\PencilBookingEvent;
use App\Models\ReservationRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConvertPencilBookingToReservationRequest
{
    public function __construct(private CreateInternalReservationRequest $createRequest, private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $selection
     */
    public function handle(Request $httpRequest, User $actor, PencilBooking $attempt, array $data, array $selection): ReservationRequest
    {
        return DB::transaction(function () use ($httpRequest, $actor, $attempt, $data, $selection): ReservationRequest {
            $booking = PencilBooking::query()->lockForUpdate()->findOrFail($attempt->id);
            if ($booking->converted_reservation_request_id !== null) {
                return ReservationRequest::query()->findOrFail($booking->converted_reservation_request_id);
            }
            if ($booking->status !== 'active') {
                throw ValidationException::withMessages(['booking' => 'Only an active pencil booking can be converted to a reservation request.']);
            }

            $result = $this->createRequest->handle(
                $httpRequest,
                $actor,
                $this->requestData($booking, $data),
                $selection,
                'pencil_booking',
                $booking,
            );
            $reservationRequest = $result['request'];
            if (! $result['created'] && $reservationRequest->pencil_booking_id !== $booking->id) {
                throw ValidationException::withMessages([
                    'idempotency_key' => 'This conversion key was already used for another reservation request.',
                ]);
            }

            $booking->forceFill([
                'status' => 'converted',
                'converted_reservation_request_id' => $reservationRequest->id,
                'converted_at' => now(),
                'updated_by' => $actor->id,
            ])->save();
            PencilBookingEvent::create([
                'pencil_booking_id' => $booking->id,
                'event_type' => 'converted',
                'details' => ['reservation_request_id' => $reservationRequest->id, 'reference' => $reservationRequest->reference],
                'actor_id' => $actor->id,
            ]);
            $this->audit->record('pencil_booking.converted', $actor, $booking, ['status' => 'active'], [
                'status' => 'converted',
                'reservation_request_id' => $reservationRequest->id,
                'reservation_request_reference' => $reservationRequest->reference,
            ]);

            return $reservationRequest;
        }, 3);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function requestData(PencilBooking $booking, array $data): array
    {
        return [
            ...$data,
            'full_name' => $data['full_name'] ?? $booking->customer_name,
            'mobile_number' => $data['mobile_number'] ?? $booking->customer_mobile,
            'email' => $data['email'] ?? $booking->customer_email,
            'event_date' => $data['event_date'] ?? $booking->getRawOriginal('event_date'),
            'event_start_time' => $data['event_start_time'] ?? $booking->event_start_time,
            'venue_information' => $data['venue_information'] ?? $booking->venue_information,
        ];
    }
}
