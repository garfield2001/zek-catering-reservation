<?php

namespace App\Http\Requests\Catalog;

use App\Models\Dish;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SaveDishRequest extends FormRequest
{
    public function authorize(): bool
    {
        $dish = $this->route('dish');

        return $this->user()->can($dish ? 'update' : 'create', $dish ?? Dish::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtolower(trim($this->input('code')))]);
        }
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
        if (is_string($this->input('description'))) {
            $this->merge(['description' => trim($this->input('description')) ?: null]);
        }
    }

    public function rules(): array
    {
        $dish = $this->route('dish');

        return [
            'code' => $dish ? ['sometimes', Rule::in([$dish->code])] : ['sometimes', 'string', 'max:80'],
            'name' => ['bail', 'required', 'string', 'max:255', function (string $attribute, mixed $value, \Closure $fail) use ($dish): void {
                if (! is_scalar($this->input('category_id'))) {
                    return;
                }
                $query = DB::table('dishes')->where('category_id', $this->input('category_id'))
                    ->whereRaw('name_key = lower(btrim(?))', [$value]);
                if ($dish instanceof Dish) {
                    $query->where('id', '!=', $dish->id);
                }
                if ($query->exists()) {
                    $fail('This food item name is already in use in the selected category.');
                }
            }],
            'description' => ['nullable', 'string', 'max:5000'],
            'category_id' => ['required', 'integer', Rule::exists('dish_categories', 'id')],
            'active' => ['required', 'boolean'],
            'family_ids' => ['present', 'array'],
            'family_ids.*' => ['required', 'integer', 'distinct', Rule::exists('product_families', 'id')->where('active', true)],
        ];
    }
}
