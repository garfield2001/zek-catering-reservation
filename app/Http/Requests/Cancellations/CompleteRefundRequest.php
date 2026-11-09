<?php

namespace App\Http\Requests\Cancellations;

use Illuminate\Foundation\Http\FormRequest;

class CompleteRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:5000'],
            'evidence' => ['nullable', 'file', 'max:10240', 'mimetypes:application/pdf,image/jpeg,image/png'],
        ];
    }
}
