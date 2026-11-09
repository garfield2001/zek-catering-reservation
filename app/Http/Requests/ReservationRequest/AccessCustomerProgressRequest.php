<?php

namespace App\Http\Requests\ReservationRequest;

use Illuminate\Foundation\Http\FormRequest;

class AccessCustomerProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reference' => ['required', 'string', 'max:32'],
            'mobile_number' => ['required', 'string', 'max:40'],
            'access_code' => ['required', 'string', 'max:64'],
        ];
    }
}
