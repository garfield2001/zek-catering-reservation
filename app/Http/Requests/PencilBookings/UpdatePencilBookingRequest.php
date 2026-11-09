<?php

namespace App\Http\Requests\PencilBookings;

class UpdatePencilBookingRequest extends StorePencilBookingRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['idempotency_key']);

        return $rules;
    }
}
