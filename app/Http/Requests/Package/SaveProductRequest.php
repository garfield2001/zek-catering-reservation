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
            'family_id' => $product ? ['required', 'integer', Rule::in([$product->family_id])] : ['required', 'integer', Rule::exists('product_families', 'id')->where('active', true)],
            'code' => $product ? ['required', 'string', Rule::in([$product->code])] : ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:_[a-z0-9]+)*$/', Rule::unique('products', 'code')->where('family_id', $this->input('family_id'))],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['sometimes', 'integer', 'between:-2147483648,2147483647'],
            'active' => ['required', 'boolean'],
        ];
    }
}
