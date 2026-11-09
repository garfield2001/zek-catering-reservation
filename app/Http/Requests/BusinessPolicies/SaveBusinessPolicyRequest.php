<?php

namespace App\Http\Requests\BusinessPolicies;

use App\Models\BusinessPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBusinessPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', BusinessPolicy::query()->where('key', 'current')->sole()) ?? false;
    }

    public function rules(): array
    {
        return [
            'minimum_advance_booking_days' => ['required', 'integer', 'between:1,365'],
            'minimum_rescheduling_notice_days' => ['required', 'integer', 'between:1,365'],
            'cancellation_notice_days' => ['required', 'integer', 'between:1,365'],
            'daily_confirmed_event_capacity' => ['required', 'integer', 'between:1,2147483647'],
            'daily_guest_capacity' => ['nullable', 'integer', 'between:1,2147483647'],
            'emergency_booking_requires_administrator' => ['required', 'boolean'],
            'quotation_validity_days' => ['required', 'integer', 'between:1,365'],
            'allow_quotation_validity_overrides' => ['required', 'boolean'],
            'reservation_payment_type' => ['required', Rule::in(['fixed_amount', 'percentage'])],
            'reservation_payment_amount' => ['nullable', 'string', 'max:30', 'regex:/^(0|[1-9][0-9]*)(?:\.[0-9]{1,2})?$/D'],
            'reservation_payment_basis_points' => ['nullable', 'integer', 'between:0,10000'],
            'reservation_payment_deadline_hours' => ['required', 'integer', 'between:1,8760'],
            'final_payment_days_before_event' => ['required', 'integer', 'between:0,365'],
            'customer_payment_upload_cutoff_days' => ['required', 'integer', 'between:0,365'],
            'payment_account_name' => ['required', 'string', 'max:255'],
            'payment_ewallet_number' => ['required', 'string', 'max:255'],
            'payment_bank_account' => ['required', 'string', 'max:255'],
            'payment_additional_instructions' => ['required', 'string', 'max:5000'],
            'menu_finalization_days_after_acceptance' => ['required', 'integer', 'between:1,365'],
            'cancellation_refund_guidance' => ['required', 'string', 'max:5000'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            if ($this->input('reservation_payment_type') === 'fixed_amount' && ! filled($this->input('reservation_payment_amount'))) {
                $validator->errors()->add('reservation_payment_amount', 'Enter the fixed reservation payment.');
            }
            if ($this->input('reservation_payment_type') === 'percentage' && $this->input('reservation_payment_basis_points') === null) {
                $validator->errors()->add('reservation_payment_basis_points', 'Enter the reservation payment percentage.');
            }
        }];
    }
}
