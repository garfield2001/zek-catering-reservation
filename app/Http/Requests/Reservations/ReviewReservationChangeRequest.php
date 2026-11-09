<?php

namespace App\Http\Requests\Reservations;

use Illuminate\Foundation\Http\FormRequest;

class ReviewReservationChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'approved' => ['required', 'boolean'],
            'review_note' => ['nullable', 'string', 'max:2000'],
            'exception_reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
