<?php

namespace App\Http\Requests\Package;

use App\Models\SelectionGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        $group = $this->route('group');

        return $this->user()->can($group ? 'update' : 'create', $group ?? SelectionGroup::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('group_code'))) {
            $this->merge(['group_code' => strtolower(trim($this->input('group_code')))]);
        }
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    public function rules(): array
    {
        $group = $this->route('group');

        return [
            'group_code' => $group ? ['required', 'string', Rule::in([$group->group_code])] : ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:_[a-z0-9]+)*$/', Rule::unique('selection_groups', 'group_code')->where('version_id', $this->route('version')?->id)],
            'name' => ['required', 'string', 'max:255'],
            'mode' => ['required', Rule::in(['fixed', 'select_one', 'select_many'])],
            'slot_kind' => ['required', Rule::in(['rice', 'drink', 'dish', 'lechon', 'tray', 'bundle_item'])],
            'minimum_count' => ['required', 'integer', 'min:0'],
            'maximum_count' => ['required', 'integer', 'min:1', 'gte:minimum_count'],
            'unique_dishes' => ['required', 'boolean'],
            'category_mode' => ['required', Rule::in(['free', 'restricted'])],
            'inclusion_count' => ['required', 'numeric', 'gt:0', 'regex:/^\d{1,9}(?:\.\d{1,3})?$/'],
            'sort_order' => ['required', 'integer', 'between:-2147483648,2147483647'],
            'options' => ['required', 'array', 'min:1'],
            'options.*.offering_option_id' => ['required', 'integer', 'distinct', Rule::exists('offering_options', 'id')],
            'options.*.is_default' => ['required', 'boolean'],
            'options.*.price_adjustment_minor' => ['required', 'regex:/^(0|[1-9][0-9]*)$/', 'max:19'],
            'category_rules' => ['present', 'array'],
            'category_rules.*.category_id' => ['required', 'integer', 'distinct', Rule::exists('dish_categories', 'id')],
            'category_rules.*.minimum_count' => ['required', 'integer', 'min:0'],
            'category_rules.*.maximum_count' => ['required', 'integer', 'min:1'],
            'dish_rules' => ['present', 'array'],
            'dish_rules.*.dish_id' => ['required', 'integer', 'distinct', Rule::exists('dishes', 'id')],
            'dish_rules.*.effect' => ['required', Rule::in(['allow', 'deny'])],
        ];
    }
}
