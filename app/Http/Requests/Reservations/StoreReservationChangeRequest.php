<?php

namespace App\Http\Requests\Reservations;

use App\Rules\StrictInteger;
use Illuminate\Foundation\Http\FormRequest;

class StoreReservationChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'event_date' => ['nullable', 'date_format:Y-m-d'],
            'event_start_time' => ['nullable', 'date_format:H:i'],
            'venue_information' => ['nullable', 'string', 'max:5000'],
            'guest_count' => ['nullable', new StrictInteger, 'integer', 'between:1,2147483647'],
            'menu_selection' => ['nullable', 'array'],
            'addon_selection' => ['nullable', 'array'],
            'request_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
