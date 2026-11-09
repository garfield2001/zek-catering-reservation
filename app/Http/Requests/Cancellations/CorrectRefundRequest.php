<?php

namespace App\Http\Requests\Cancellations;

use Illuminate\Foundation\Http\FormRequest;

class CorrectRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'correction_pesos' => ['required', 'string', 'max:30', 'regex:/^-?(?:0|[1-9][0-9]*)(?:\.[0-9]{1,2})?$/D', 'not_in:0,0.0,0.00,-0,-0.0,-0.00'],
            'reason' => ['required', 'string', 'max:5000'],
        ];
    }
}
