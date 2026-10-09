<?php

namespace App\Http\Requests\Access;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SaveUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('user') ? $this->user()->can('update', $this->route('user')) : $this->user()->can('create', User::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($this->route('user'))],
            'password' => [$this->route('user') ? 'nullable' : 'required', 'confirmed', Password::min(12)->mixedCase()->numbers(), 'max:128'],
            'active' => ['required', 'boolean'],
            'role' => ['required', Rule::in($this->user()->isAdmin() ? ['admin', 'staff'] : ['staff'])],
            'access_version' => [$this->route('user') ? 'required' : 'nullable', 'integer', 'min:1'],
        ];
    }
}
