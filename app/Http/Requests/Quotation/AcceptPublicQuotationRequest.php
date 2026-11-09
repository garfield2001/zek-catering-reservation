<?php

namespace App\Http\Requests\Quotation;

use Illuminate\Foundation\Http\FormRequest;

class AcceptPublicQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['accepted_by_name' => ['required', 'string', 'max:255'], 'confirm_terms' => ['required', 'accepted']];
    }
}
