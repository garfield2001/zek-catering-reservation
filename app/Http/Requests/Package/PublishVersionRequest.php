<?php

namespace App\Http\Requests\Package;

use App\Models\PackageVersion;
use Illuminate\Foundation\Http\FormRequest;

class PublishVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('publish', PackageVersion::class);
    }

    public function rules(): array
    {
        return ['draft_revision' => ['required', 'integer', 'min:1'],
            'confirm' => ['required', 'accepted']];
    }
}
