<?php

namespace App\Http\Requests\ReservationRequest;

use App\Models\ReservationRequest;
use App\Rules\StrictInteger;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInternalReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ReservationRequest::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'full_name' => ['required', 'string', 'max:160'],
            'mobile_number' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email:rfc', 'max:254'],
            'event_date' => ['required', 'date_format:Y-m-d', $this->notPastDate()],
            'event_start_time' => ['required', 'date_format:H:i'],
            'venue_information' => ['required', 'string', 'max:2000'],
            'event_notes' => ['nullable', 'string', 'max:5000'],
            'selections' => ['required', 'array', 'min:1'],
            'selections.*' => ['required', 'array:type,product_id,listing_id,version_id,quantity,groups,selling_option_id'],
            'selections.*.type' => ['required', Rule::in(['package', 'listing'])],
            'selections.*.product_id' => ['nullable', new StrictInteger, 'integer', 'min:1', 'required_if:selections.*.type,package'],
            'selections.*.listing_id' => ['nullable', new StrictInteger, 'integer', 'min:1', 'required_if:selections.*.type,listing'],
            'selections.*.version_id' => ['required', new StrictInteger, 'integer', 'min:1'],
            'selections.*.quantity' => ['required', new StrictInteger, 'integer', 'min:1'],
            'selections.*.groups' => ['nullable', 'array'],
            'selections.*.groups.*' => ['required', 'array:quantity,selections'],
            'selections.*.groups.*.quantity' => ['required', new StrictInteger, 'integer', 'min:1'],
            // Published add-on packages use their own stable selection-group
            // codes. Do not whitelist the Main Catering wire keys here; the
            // shared package validator resolves and rejects unknown groups.
            'selections.*.groups.*.selections' => ['required', 'array'],
            'selections.*.groups.*.selections.food_items' => ['nullable', 'array'],
            'selections.*.groups.*.selections.food_items.*' => [new StrictInteger, 'integer', 'min:1'],
            'selections.*.groups.*.selections.food_sizes' => ['nullable', 'array'],
            'selections.*.selling_option_id' => ['nullable', new StrictInteger, 'integer', 'min:1'],
        ];
    }

    private function notPastDate(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                return;
            }

            try {
                $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'Asia/Manila');
            } catch (\Throwable) {
                return;
            }

            if ($date->isBefore(CarbonImmutable::now('Asia/Manila')->startOfDay())) {
                $fail('The event date must be today or a future date.');
            }
        };
    }
}
