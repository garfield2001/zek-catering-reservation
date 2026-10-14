<?php

namespace App\Http\Requests\Package;

use Illuminate\Foundation\Http\FormRequest;

class SavePackageMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('version'));
    }

    public function rules(): array
    {
        return PackageInputRules::menu();
    }
}
