<?php

namespace App\Http\Requests\Catalog;

use App\Enums\Catalog\CollectionBehavior;
use App\Models\ProductFamily;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CreateCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', ProductFamily::class);
    }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'description'] as $field) {
            if (is_string($this->input($field))) {
                $value = trim($this->input($field));
                $this->merge([$field => $field === 'description' && $value === '' ? null : $value]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'max:255', function (string $attribute, mixed $value, \Closure $fail): void {
                if (DB::table('product_families')->whereNull('archived_at')->whereRaw('lower(btrim(name)) = lower(btrim(?))', [$value])->exists()) {
                    $fail('This collection name is already in use.');
                }
            }],
            'description' => ['nullable', 'string', 'max:5000'],
            'kind' => ['required', Rule::in(['primary', 'addon'])],
            'behavior_type' => ['required', Rule::in(array_map(fn (CollectionBehavior $behavior) => $behavior->value, CollectionBehavior::cases()))],
            'sort_order' => ['sometimes', 'integer', 'between:-2147483648,2147483647'],
            'active' => ['required', 'boolean'],
            'package_configuration' => [Rule::requiredIf($this->input('kind') === 'addon' && $this->input('behavior_type') !== 'direct_sale'), 'array'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            $kind = $this->input('kind');
            $behavior = $this->input('behavior_type');
            if ($kind === 'primary' && $behavior !== CollectionBehavior::MainPackage->value) {
                $validator->errors()->add('behavior_type', 'The primary Collection must use the configured primary catering behavior.');
            }
            if ($kind === 'addon' && $behavior === CollectionBehavior::MainPackage->value) {
                $validator->errors()->add('behavior_type', 'The primary catering behavior cannot be used for an add-on Collection.');
            }
        }];
    }
}
