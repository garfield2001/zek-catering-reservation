<?php

namespace App\Http\Requests\Catalog;

use App\Models\IndividualListing;
use App\Services\Catalog\ListingConfiguration;
use Illuminate\Foundation\Http\FormRequest;

class SaveListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('listing') ? 'update' : 'create', $this->route('listing') ?? IndividualListing::class);
    }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'description', 'selling_unit'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }
    }

    public function rules(): array
    {
        $editing = $this->route('listing') !== null;

        return ['family_id' => ['required', 'integer', 'exists:product_families,id'], 'dish_id' => ['required', 'integer', 'exists:dishes,id'],
            'offering_option_id' => ['nullable', 'integer', 'exists:offering_options,id'],
            'creation_token' => [$editing ? 'prohibited' : 'required', 'uuid'],
            'version_id' => [$editing ? 'required' : 'prohibited', 'integer'], 'draft_revision' => [$editing ? 'required' : 'prohibited', 'integer', 'min:1'],
            'name' => ['nullable', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000'],
            'selling_unit' => ListingConfiguration::sellingUnitRules(),
            'price_pesos' => ['required_without:selling_options', 'nullable', 'string', 'regex:/^(0|[1-9][0-9]*)(?:\.[0-9]{1,2})?$/', 'max:20'],
            'selling_options' => ['sometimes', 'required', 'array', 'list', 'min:1', 'max:100'],
            'minimum_quantity' => ['required', 'integer', 'between:1,2147483647'], 'quantity_step' => ['required', 'integer', 'between:1,2147483647'],
            'maximum_quantity' => ['nullable', 'integer', 'between:1,2147483647', 'gte:minimum_quantity']];
    }
}
