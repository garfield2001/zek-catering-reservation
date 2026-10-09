<?php

namespace App\Http\Requests\Catalog;

use App\Enums\Catalog\ServingUnit;
use App\Models\OfferingOption;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveOfferingOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $option = $this->route('option');

        return $this->user()->can($option ? 'update' : 'create', $option ?? OfferingOption::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('option_code'))) {
            $this->merge(['option_code' => strtolower(trim($this->input('option_code')))]);
        }
        if (is_string($this->input('label'))) {
            $this->merge(['label' => trim($this->input('label'))]);
        }
    }

    public function rules(): array
    {
        $option = $this->route('option');

        return [
            'option_code' => $option ? ['required', Rule::in([$option->option_code])] : ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:_[a-z0-9]+)*$/',
                Rule::unique('offering_options', 'option_code')->where('offering_id', $this->route('offering')?->id)],
            'label' => ['required', 'string', 'max:255'],
            'unit' => ['required', Rule::enum(ServingUnit::class)],
            'quantity' => ['required', 'numeric', 'gt:0', 'regex:/^\d{1,9}(?:\.\d{1,3})?$/'],
            'serving_pax' => ['nullable', 'integer', 'between:1,2147483647'],
            'serving_claim' => ['nullable', 'string', 'max:255'],
            'active' => ['required', 'boolean'],
        ];
    }
}
