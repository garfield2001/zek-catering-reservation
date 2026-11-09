<?php

namespace App\Http\Requests\Quotation;

use App\Models\QuotationTermsVersion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveQuotationTermsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $version = $this->route('quotationTermsVersion');

        return $version === null
            ? ($this->user()?->can('create', QuotationTermsVersion::class) ?? false)
            : ($this->user()?->can('update', $version) ?? false);
    }

    public function rules(): array
    {
        return [
            'tax_price_treatment_statement' => ['nullable', 'string', 'max:5000'],
            'reservation_payment_type' => ['nullable', 'string', Rule::in(['fixed_amount', 'percentage'])],
            'reservation_payment_amount' => ['nullable', 'string', 'max:30', 'regex:/^(0|[1-9][0-9]*)(?:\.[0-9]{1,2})?$/D'],
            'reservation_payment_basis_points' => ['nullable', 'integer', 'between:0,10000'],
            'payment_deadline_hours' => ['required', 'integer', 'between:1,8760'],
            'menu_finalization_days' => ['required', 'integer', 'between:1,365'],
            'menu_finalization_terms' => ['nullable', 'string', 'max:5000'],
            'cancellation_refund_terms' => ['nullable', 'string', 'max:5000'],
            'rescheduling_terms' => ['nullable', 'string', 'max:5000'],
            'customer_conditions' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
