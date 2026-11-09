<?php

namespace App\Http\Requests\Quotation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplyDirectQuotationAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('applyDirectAdjustment', $this->route('quotationVersion')) ?? false;
    }

    public function rules(): array
    {
        return [
            'adjustment_amount' => ['required', 'string', 'regex:/^(0|[1-9][0-9]*)(?:\.[0-9]{1,2})?$/D'],
            'direction' => ['required', 'string', Rule::in(['discount', 'surcharge'])],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
