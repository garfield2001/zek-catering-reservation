<?php

namespace App\Http\Requests\Payments;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('record', Payment::class) ?? false;
    }

    public function rules(): array
    {
        return ['quotation_id' => ['nullable', 'integer', 'exists:quotations,id'], 'reservation_request_id' => ['nullable', 'integer', 'exists:reservation_requests,id'], 'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'], 'amount' => ['required', 'numeric', 'gt:0', 'max:99999999'], 'paid_at' => ['required', 'date'], 'transaction_reference' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string', 'max:2000'], 'exception_reason' => ['nullable', 'string', 'max:2000'], 'idempotency_key' => ['required', 'uuid'], 'proof' => ['nullable', 'file', 'max:10240', 'mimetypes:application/pdf,image/jpeg,image/png']];
    }
}
