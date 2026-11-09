<?php

namespace App\Http\Requests\ReservationRequest;

use Illuminate\Foundation\Http\FormRequest;

class AcceptTrackedQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'accepted_by_name' => ['required', 'string', 'max:255'],
            'confirm_quotation' => ['required', 'accepted'],
        ];
    }
}
