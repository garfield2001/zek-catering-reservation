<?php

namespace App\Http\Requests\Catalog;

use App\Models\DishOffering;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveOfferingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $offering = $this->route('offering');

        return $this->user()->can($offering ? 'update' : 'create', $offering ?? DishOffering::class);
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
        $routeOffering = $this->route('offering');
        $offering = $routeOffering instanceof DishOffering ? $routeOffering : null;

        return [
            'family_id' => $offering ? ['required', Rule::in([$offering->family_id])] : ['required', 'integer', Rule::exists('product_families', 'id')->where('active', true)],
            'code' => $offering ? ['required', Rule::in([$offering->code])] : ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:_[a-z0-9]+)*$/',
                Rule::unique('dish_offerings', 'code')->where('dish_id', $this->route('dish')?->id)->where('family_id', $this->input('family_id'))],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'active' => ['required', 'boolean'],
        ];
    }
}
