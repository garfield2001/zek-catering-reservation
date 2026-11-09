<?php

namespace App\Http\Requests\Cancellations;

use App\Rules\StrictInteger;
use Illuminate\Foundation\Http\FormRequest;

class StoreRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'payment_id' => ['required', new StrictInteger, 'integer', 'exists:payments,id'],
            'amount_pesos' => ['required', 'string', 'max:30', 'regex:/^(0|[1-9][0-9]*)(?:\.[0-9]{1,2})?$/D'],
            'reason' => ['required', 'string', 'max:5000'],
            'internal_note' => ['nullable', 'string', 'max:5000'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
