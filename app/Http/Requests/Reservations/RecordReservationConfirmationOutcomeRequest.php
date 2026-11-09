<?php

namespace App\Http\Requests\Reservations;

use App\Models\Reservation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordReservationConfirmationOutcomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('confirm', Reservation::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'outcome' => ['required', Rule::in(['deferred', 'declined'])],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
