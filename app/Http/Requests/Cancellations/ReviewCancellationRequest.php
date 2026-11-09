<?php

namespace App\Http\Requests\Cancellations;

use Illuminate\Foundation\Http\FormRequest;

class ReviewCancellationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('review', $this->route('cancellationRequest')) ?? false;
    }

    public function rules(): array
    {
        return ['approved' => ['required', 'boolean'], 'review_note' => ['required', 'string', 'max:5000']];
    }
}
