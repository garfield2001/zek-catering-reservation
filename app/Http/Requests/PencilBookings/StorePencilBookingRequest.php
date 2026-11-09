<?php

namespace App\Http\Requests\PencilBookings;

use App\Models\PencilBooking;
use Illuminate\Foundation\Http\FormRequest;

class StorePencilBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', PencilBooking::class) ?? false;
    }

    public function rules(): array
    {
        return ['idempotency_key' => ['required', 'uuid'], 'customer_name' => ['required', 'string', 'max:160'], 'customer_mobile' => ['nullable', 'string', 'max:40', 'required_without:customer_email'], 'customer_email' => ['nullable', 'email:rfc', 'max:254', 'required_without:customer_mobile'], 'event_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'], 'event_start_time' => ['nullable', 'date_format:H:i'], 'venue_information' => ['nullable', 'string', 'max:2000'], 'guest_count' => ['nullable', 'integer', 'min:1'], 'notes' => ['nullable', 'string', 'max:5000'], 'follow_up_on' => ['nullable', 'date_format:Y-m-d']];
    }
}
