<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFamilyOrderDefaultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('family')->orderDefault);
    }

    public function rules(): array
    {
        return [
            'minimum_quantity' => ['required', 'integer', 'between:1,2147483647'],
            'quantity_step' => ['required', 'integer', 'between:1,2147483647'],
            'maximum_quantity' => ['nullable', 'integer', 'between:1,2147483647', 'gte:minimum_quantity'],
            'enforce_minimum_floor' => ['required', 'boolean'],
        ];
    }
}
