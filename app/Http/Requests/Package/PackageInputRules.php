<?php

namespace App\Http\Requests\Package;

use Illuminate\Validation\Rule;

/** Shared HTTP field rules for the cohesive editor and retained authorized endpoints. */
class PackageInputRules
{
    public static function menu(): array
    {
        return ['draft_revision' => ['required', 'integer', 'min:1'], 'total_selection_count' => ['required', 'integer', 'min:1'],
            'food_item_ids' => ['required', 'array', 'min:1'], 'food_item_ids.*' => ['required', 'integer', 'distinct', 'exists:dishes,id'],
            'presentation' => ['sometimes', 'nullable', 'array'],
            'serving_default' => ['sometimes', 'nullable', 'array'], 'food_sizes' => ['sometimes', 'array'],
            'food_details' => ['sometimes', 'array'], 'food_details.*.is_fixed' => ['sometimes', 'boolean'],
            'food_details.*.included_quantity' => ['nullable', 'numeric', 'gt:0', 'max:999999999.999', 'decimal:0,3'],
            // New fixed portions use the active Measurement Unit catalog. The
            // text field remains only to preserve legacy Draft facts.
            'food_details.*.included_unit' => ['nullable', 'string', 'max:20'],
            'food_details.*.included_unit_id' => ['nullable', 'integer'],
            'food_details.*.price_adjustment_pesos' => ['sometimes', 'string', 'regex:/^(0|[1-9][0-9]*)(?:\\.[0-9]{1,2})?$/', 'max:20'],
            'required_category_ids' => ['present', 'array'], 'required_category_ids.*' => ['required', 'integer', 'distinct', 'exists:dish_categories,id']];
    }

    public static function pricing(): array
    {
        return ['price_basis' => ['sometimes', Rule::in(['per_pax', 'per_pack', 'per_set'])], 'base_price_minor' => ['required_without:base_price_pesos', 'prohibits:base_price_pesos', 'regex:/^(0|[1-9][0-9]*)$/', 'max:19'],
            'base_price_pesos' => ['required_without:base_price_minor', 'prohibits:base_price_minor', 'string', 'regex:/^(0|[1-9][0-9]*)(?:\.[0-9]{1,2})?$/', 'max:20'],
            'selection_policy' => ['required', Rule::in(['uniform', 'split'])], 'minimum_quantity' => ['required', 'integer', 'between:1,2147483647'],
            'quantity_step' => ['required', 'integer', 'between:1,2147483647'],
            'maximum_quantity' => ['nullable', 'integer', 'between:1,2147483647'], 'estimated_capacity' => ['nullable', 'integer', 'between:1,2147483647'],
            'draft_revision' => ['required', 'integer', 'min:1']];
    }
}
