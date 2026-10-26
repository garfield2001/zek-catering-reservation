<?php

namespace App\Http\Requests\Storefront;

use App\Rules\StrictInteger;
use Illuminate\Foundation\Http\FormRequest;

class PreviewPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', new StrictInteger, 'integer', 'between:1,2147483647'],
            'groups' => ['required', 'array', 'min:1'],
            'groups.*.quantity' => ['required', new StrictInteger, 'integer', 'between:1,2147483647'],
            'groups.*.selections' => ['required', 'array'],
        ];
    }
}
