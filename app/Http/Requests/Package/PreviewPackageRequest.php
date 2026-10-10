<?php

namespace App\Http\Requests\Package;

use Illuminate\Foundation\Http\FormRequest;

class PreviewPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('version'));
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'between:1,2147483647'],
            'groups' => ['required', 'array', 'min:1'],
            'groups.*.quantity' => ['required', 'integer', 'between:1,2147483647'],
            'groups.*.selections' => ['required', 'array'],
        ];
    }
}
