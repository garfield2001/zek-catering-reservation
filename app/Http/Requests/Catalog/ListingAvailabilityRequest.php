<?php

namespace App\Http\Requests\Catalog;

use App\Models\IndividualListing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListingAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('lifecycle', IndividualListing::class);
    }

    public function rules(): array
    {
        return ['operation' => ['required', Rule::in(['enable', 'disable', 'archive'])], 'confirm' => ['required_if:operation,archive', 'accepted_if:operation,archive']];
    }
}
