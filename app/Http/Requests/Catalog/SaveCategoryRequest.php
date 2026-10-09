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
            'code' => $category ? ['required', Rule::in([$category->code])] : ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:_[a-z0-9]+)*$/', Rule::unique('dish_categories', 'code')],
            'name' => ['bail', 'required', 'string', 'max:255', function (string $attribute, mixed $value, \Closure $fail) use ($category): void {
                $query = DB::table('dish_categories')->whereRaw('name_key = lower(btrim(?))', [$value]);
                if ($category instanceof DishCategory) {
                    $query->where('id', '!=', $category->id);
                }
                if ($query->exists()) {
                    $fail('This category name is already in use.');
                }
            }],
            'sort_order' => ['required', 'integer', 'between:-2147483648,2147483647'],
            'active' => ['required', 'boolean'],
        ];
    }
}
