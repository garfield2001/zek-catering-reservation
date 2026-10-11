<?php

namespace App\Http\Requests\Catalog;

use App\Models\DishCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
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

        return [
            'code' => $category ? ['sometimes', Rule::in([$category->code])] : ['sometimes', 'string', 'max:80'],
            'name' => ['bail', 'required', 'string', 'max:255', function (string $attribute, mixed $value, \Closure $fail) use ($category): void {
                $query = DB::table('dish_categories')->whereRaw('name_key = lower(btrim(?))', [$value]);
                if ($category instanceof DishCategory) {
                    $query->where('id', '!=', $category->id);
                }
                if ($query->exists()) {
                    $fail('This category name is already in use.');
                }
            }],
            'sort_order' => ['sometimes', 'integer', 'between:-2147483648,2147483647'],
            'active' => ['required', 'boolean'],
        ];
    }
}
