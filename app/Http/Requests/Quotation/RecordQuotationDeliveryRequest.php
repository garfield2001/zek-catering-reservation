<?php

namespace App\Http\Requests\Quotation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordQuotationDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('recordDelivery', $this->route('quotationVersion')) ?? false;
    }

    public function rules(): array
    {
        return [
            'channel' => ['required', 'string', Rule::in(['manual_link', 'sms', 'email', 'printed', 'other'])],
            'destination_hint' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
