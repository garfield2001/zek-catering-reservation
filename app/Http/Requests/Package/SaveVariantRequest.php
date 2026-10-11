<?php

namespace App\Http\Requests\Package;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantVersion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SaveVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        $variant = $this->route('variant');

        return $this->user()->can($variant ? 'update' : 'create', $variant ?? ProductVariant::class)
            && $this->user()->can('price', VariantVersion::class);
    }

    protected function prepareForValidation(): void
    {
        foreach (['code', 'name', 'description'] as $key) {
            if (is_string($this->input($key))) {
                $value = trim($this->input($key));
                $this->merge([$key => $key === 'code' ? strtolower($value) : ($key === 'description' && $value === '' ? null : $value)]);
            }
        }
    }

    public function rules(): array
    {
        $variant = $this->route('variant');

        return [
            'code' => $variant ? ['sometimes', 'string', Rule::in([$variant->code])] : ['sometimes', 'string', 'max:80'],
            'name' => ['bail', 'required', 'string', 'max:255', function (string $attribute, mixed $value, \Closure $fail) use ($variant): void {
                $product = $this->route('product');
                if (! $product instanceof Product) {
                    return;
                }
                $query = DB::table('product_variants')->where('product_id', $product->id)
                    ->whereRaw('lower(btrim(name)) = lower(btrim(?))', [$value]);
                if ($variant instanceof ProductVariant) {
                    $query->where('id', '!=', $variant->id);
                }
                if ($query->exists()) {
                    $fail('This variant display name is already in use for the product.');
                }
            }],
            'description' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['sometimes', 'integer', 'between:-2147483648,2147483647'],
            'active' => ['required', 'boolean'],
            'base_price_minor' => ['required_without:base_price_pesos', 'prohibits:base_price_pesos', 'regex:/^(0|[1-9][0-9]*)$/', 'max:19'],
            'base_price_pesos' => ['required_without:base_price_minor', 'prohibits:base_price_minor', 'string', 'regex:/^(0|[1-9][0-9]*)(?:\.[0-9]{1,2})?$/', 'max:20'],
            'selection_policy' => ['required', Rule::in(['uniform', 'split'])],
            'minimum_override' => ['nullable', 'integer', 'between:1,2147483647'],
            'step_override' => ['nullable', 'integer', 'between:1,2147483647'],
            'maximum_override_enabled' => ['required', 'boolean'],
            'maximum_override' => ['nullable', 'integer', 'between:1,2147483647'],
            'draft_revision' => $variant ? ['required', 'integer', 'min:1'] : ['prohibited'],
        ];
    }
}
