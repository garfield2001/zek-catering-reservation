<?php

namespace App\Http\Requests\Payments;

use App\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;

class SavePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageMethods', PaymentMethod::class) ?? false;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:100'], 'instructions' => ['nullable', 'string', 'max:4000'], 'destination_information' => ['nullable', 'string', 'max:2000'], 'is_offline' => ['required', 'boolean'], 'is_active' => ['required', 'boolean'], 'display_order' => ['required', 'integer', 'min:0']];
    }
}
