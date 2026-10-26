<?php

namespace App\Http\Requests\Catalog;

use App\Models\IndividualListing;
use App\Models\ProductFamily;
use App\Services\Catalog\ListingConfiguration;
use Illuminate\Foundation\Http\FormRequest;

class BulkCreateListingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', IndividualListing::class)
            && $this->user()->can('price', IndividualListing::class)
            && $this->route('family') instanceof ProductFamily;
    }

    public function rules(): array
    {
        return [
            'listings' => ['required', 'array', 'list', 'min:1', 'max:100'],
            'listings.*.dish_id' => ['required', 'integer', 'distinct', 'exists:dishes,id'],
            'listings.*.creation_token' => ['required', 'uuid', 'distinct'],
            'listings.*.selling_unit' => ListingConfiguration::sellingUnitRules(),
            'listings.*.price_pesos' => ['required', 'string', 'regex:/^(0|[1-9][0-9]*)(?:\.[0-9]{1,2})?$/', 'max:20'],
            'listings.*.minimum_quantity' => ['required', 'integer', 'between:1,2147483647'],
            'listings.*.quantity_step' => ['required', 'integer', 'between:1,2147483647'],
            'listings.*.maximum_quantity' => ['nullable', 'integer', 'between:1,2147483647', 'gte:listings.*.minimum_quantity'],
        ];
    }
}
