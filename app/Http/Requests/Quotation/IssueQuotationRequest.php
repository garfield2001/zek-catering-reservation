<?php

namespace App\Http\Requests\Quotation;

use Illuminate\Foundation\Http\FormRequest;

class IssueQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('issue', $this->route('quotationVersion')) ?? false;
    }

    public function rules(): array
    {
        return [
            'valid_until' => ['nullable', 'date', 'after:now'],
        ];
    }
}
