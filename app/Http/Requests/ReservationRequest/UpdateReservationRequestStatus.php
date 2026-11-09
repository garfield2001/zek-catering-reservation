<?php

namespace App\Http\Requests\ReservationRequest;

use App\Enums\ReservationRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReservationRequestStatus extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('reservationRequest')) ?? false;
    }

    public function rules(): array
    {
        return ['status' => ['required', 'string', Rule::enum(ReservationRequestStatus::class)]];
    }
}
