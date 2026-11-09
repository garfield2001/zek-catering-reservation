<?php

namespace App\Http\Requests\Reservations;

use App\Models\Reservation;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('confirm', Reservation::class) ?? false;
    }

    public function rules(): array
    {
        return ['guest_count' => ['nullable', 'integer', 'between:1,2147483647']];
    }
}
