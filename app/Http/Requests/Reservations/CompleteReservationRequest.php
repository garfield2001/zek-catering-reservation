<?php

namespace App\Http\Requests\Reservations;

use App\Models\Reservation;
use Illuminate\Foundation\Http\FormRequest;

class CompleteReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $reservation = $this->route('reservation');

        return $reservation instanceof Reservation && ($this->user()?->can('complete', $reservation) ?? false);
    }

    public function rules(): array
    {
        return ['note' => ['nullable', 'string', 'max:2000']];
    }
}
