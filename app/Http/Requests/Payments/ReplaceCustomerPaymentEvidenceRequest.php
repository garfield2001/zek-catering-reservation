<?php

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;

class ReplaceCustomerPaymentEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['proof' => ['required', 'image', 'max:10240', 'mimetypes:image/jpeg,image/png,image/webp']];
    }
}
