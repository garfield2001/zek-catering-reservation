<?php

namespace App\Http\Requests\Catalog;

use App\Models\DishCategory;
use App\Rules\AvailableCategoryName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('category');

        return $this->user()->can($category ? 'update' : 'create', $category ?? DishCategory::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtolower(trim($this->input('code')))]);
        }
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    public function rules(): array
    {
        $category = $this->route('category');

        return ['return_product_id' => ['nullable', 'integer', 'exists:products,id'],
            'code' => $category ? ['sometimes', Rule::in([$category->code])] : ['sometimes', 'string', 'max:80'],
            'name' => ['bail', 'required', 'string', 'max:255', new AvailableCategoryName($category instanceof DishCategory ? $category->id : null)],
            'sort_order' => ['sometimes', 'integer', 'between:-2147483648,2147483647'],
            'active' => ['required', 'boolean'],
        ];
    }
}
