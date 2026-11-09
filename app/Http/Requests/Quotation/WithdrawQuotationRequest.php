<?php

namespace App\Http\Requests\Quotation;

use Illuminate\Foundation\Http\FormRequest;

class WithdrawQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('withdraw', $this->route('quotationVersion')) ?? false;
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:2000']];
    }
}
