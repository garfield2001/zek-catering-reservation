<?php

namespace App\Http\Requests\PencilBookings;

use App\Models\PencilBooking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClosePencilBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', PencilBooking::class) ?? false;
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::in(['closed', 'did_not_proceed'])], 'reason' => ['required', 'string', 'max:5000']];
    }
}
