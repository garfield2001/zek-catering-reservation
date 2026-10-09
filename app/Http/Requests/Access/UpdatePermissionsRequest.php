<?php

namespace App\Http\Requests\Access;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('permissions', $this->route('user'));
    }

    public function rules(): array
    {
        return [
            'access_version' => ['required', 'integer', 'min:1'],
            'overrides' => ['present', 'array'],
            'overrides.*' => ['required', 'array:code,effect'],
            'overrides.*.code' => ['required', 'string', 'distinct', Rule::exists('permissions', 'code')],
            'overrides.*.effect' => ['required', Rule::in(['allow', 'deny'])],
        ];
    }
}
