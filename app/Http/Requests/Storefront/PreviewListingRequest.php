<?php

namespace App\Http\Requests\Storefront;

use App\Rules\StrictInteger;
use Illuminate\Foundation\Http\FormRequest;

class PreviewListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', new StrictInteger, 'integer', 'between:1,2147483647'],
            'selling_option_id' => ['nullable', new StrictInteger, 'integer'],
        ];
    }
}
