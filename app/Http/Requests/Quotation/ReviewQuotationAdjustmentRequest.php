<?php

namespace App\Http\Requests\Quotation;

use Illuminate\Foundation\Http\FormRequest;

class ReviewQuotationAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reviewAdjustment', $this->route('quotationAdjustmentRequest')) ?? false;
    }

    public function rules(): array
    {
        return ['approved' => ['required', 'boolean'], 'review_note' => ['nullable', 'string', 'max:2000']];
    }
}
