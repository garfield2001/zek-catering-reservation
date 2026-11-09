<?php

namespace App\Http\Requests\Reservations;

use App\Models\Reservation;
use Illuminate\Foundation\Http\FormRequest;

class SaveReservationCapacityOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageCapacity', Reservation::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'event_date' => ['required', 'date'],
            'maximum_confirmed_events' => ['required', 'integer', 'between:1,2147483647'],
            'maximum_guests' => ['nullable', 'integer', 'between:1,2147483647'],
        ];
    }
}
