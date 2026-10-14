<?php

namespace App\Http\Requests\Catalog;

use App\Models\IndividualListing;
use Illuminate\Foundation\Http\FormRequest;

class PublishListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('publish', IndividualListing::class);
    }

    public function rules(): array
    {
        return ['draft_revision' => ['required', 'integer', 'min:1'], 'confirm' => ['required', 'accepted']];
    }
}
