<?php

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;

class ReallocatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('verify', $this->route('payment')) ?? false;
    }

    public function rules(): array
    {
        return ['quotation_version_id' => ['required', 'integer', 'exists:quotation_versions,id'], 'reason' => ['required', 'string', 'max:2000']];
    }
}
