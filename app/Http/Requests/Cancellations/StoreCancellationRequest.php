<?php

namespace App\Http\Requests\Cancellations;

use Illuminate\Foundation\Http\FormRequest;

class StoreCancellationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'reason' => ['required', 'string', 'max:5000'],
            'internal_note' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
