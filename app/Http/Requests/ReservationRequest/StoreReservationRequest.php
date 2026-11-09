<?php

namespace App\Http\Requests\ReservationRequest;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'full_name' => ['required', 'string', 'max:160'],
            'mobile_number' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email:rfc', 'max:254'],
            'event_date' => ['required', 'date_format:Y-m-d', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_string($value) || ! preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $value)) {
                    return;
                }

                try {
                    $eventDate = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'Asia/Manila');
                } catch (\Throwable) {
                    return;
                }

                if ($eventDate->isBefore(CarbonImmutable::now('Asia/Manila')->startOfDay())) {
                    $fail('The event date must be today or a future date.');
                }
            }],
            'event_start_time' => ['required', 'date_format:H:i'],
            'venue_information' => ['required', 'string', 'max:2000'],
            'event_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
