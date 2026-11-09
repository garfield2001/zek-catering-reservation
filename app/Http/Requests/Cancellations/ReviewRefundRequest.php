<?php

namespace App\Http\Requests\Cancellations;

use Illuminate\Foundation\Http\FormRequest;

class ReviewRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return ['approved' => ['required', 'boolean'], 'review_note' => ['nullable', 'string', 'max:5000']];
    }
}
