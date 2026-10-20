<?php

namespace App\Http\Requests\Package;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $this->user()->can($product instanceof Product ? 'update' : 'create', $product instanceof Product ? $product : Product::class);
    }

    protected function prepareForValidation(): void
    {
        foreach (['code', 'name'] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => $key === 'code' ? strtolower(trim($this->input($key))) : trim($this->input($key))]);
            }
        }
    }

    public function rules(): array
    {
        $routeProduct = $this->route('product');
        $product = $routeProduct instanceof Product ? $routeProduct : null;

        return [
            'family_id' => $this->routeIs('catering.store') ? ['prohibited'] : ($product ? ['required', 'integer', Rule::in([$product->family_id])] : ['required', 'integer', Rule::exists('product_families', 'id')->where('active', true)->where(fn ($q) => $q->where(fn ($q) => $q->where('is_addon', false)->orWhereNotNull('package_format')))]),
            'code' => $product ? ['sometimes', 'string', Rule::in([$product->code])] : ['sometimes', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['sometimes', 'integer', 'between:-2147483648,2147483647'],
            'active' => ['required', 'boolean'],
            'creation_token' => $product ? ['prohibited'] : ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return ['family_id.exists' => 'Choose an available service with its package format configured. Edit the service to set how foods are provided before creating a package.'];
    }
}
