<?php

namespace App\Http\Requests\Package;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCompletePackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $this->user()->can($product instanceof Product ? 'update' : 'create', $product instanceof Product ? $product : Product::class);
    }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'description'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }
    }

    public function rules(): array
    {
        $product = $this->route('product');

        return [...PackageInputRules::menu(), ...PackageInputRules::pricing(),
            'family_id' => ['required', 'integer', $product instanceof Product ? Rule::in([$product->family_id]) : 'exists:product_families,id'],
            'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000'],
            'active' => ['required', 'boolean'],
            'save_action' => ['sometimes', Rule::in(['draft', 'publish'])],
            'confirm_publication' => ['exclude_unless:save_action,publish', 'required', 'accepted'],
            'creation_token' => $product instanceof Product ? ['prohibited'] : ['required', 'uuid'],
            'version_id' => $product instanceof Product ? ['required', 'integer', 'min:1'] : ['prohibited'],
            'draft_revision' => $product instanceof Product ? ['required', 'integer', 'min:1'] : ['prohibited'],
        ];
    }
}
