<?php

namespace App\Http\Requests\Quotation;

use Illuminate\Foundation\Http\FormRequest;

class ApproveQuotationTermsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('approve', $this->route('quotationTermsVersion')) ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
