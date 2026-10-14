<?php

namespace App\Http\Requests\Package;

use App\Models\PackageVersion;
use Illuminate\Foundation\Http\FormRequest;

class SavePackageVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('version')) && $this->user()->can('price', PackageVersion::class);
    }

    public function rules(): array
    {
        return PackageInputRules::pricing();
    }
}
