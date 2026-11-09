<?php

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;

class ReviewPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('verify', $this->route('payment')) ?? false;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'in:verify,reject,reverse'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'quotation_version_id' => ['nullable', 'integer', 'exists:quotation_versions,id'],
            'verified_amount' => ['nullable', 'string', 'max:30', 'regex:/^(0|[1-9][0-9]*)(?:\.[0-9]{1,2})?$/D'],
            'customer_message' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            if (in_array($this->input('action'), ['reject', 'reverse'], true) && trim((string) $this->input('reason')) === '') {
                $validator->errors()->add('reason', 'Provide a meaningful reason for this financial correction.');
            }
            if ($this->input('action') === 'verify' && ! filled($this->input('verified_amount'))) {
                $validator->errors()->add('verified_amount', 'Enter the amount actually received before verifying this receipt.');
            }
        }];
    }
}
