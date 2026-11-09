<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessPolicy extends Model
{
    protected $guarded = ['id', 'key'];

    protected function casts(): array
    {
        return [
            'minimum_advance_booking_days' => 'integer',
            'minimum_rescheduling_notice_days' => 'integer',
            'cancellation_notice_days' => 'integer',
            'event_duration_hours' => 'integer',
            'daily_confirmed_event_capacity' => 'integer',
            'daily_guest_capacity' => 'integer',
            'emergency_booking_requires_administrator' => 'boolean',
            'quotation_validity_days' => 'integer',
            'allow_quotation_validity_overrides' => 'boolean',
            'reservation_payment_amount' => 'string',
            'reservation_payment_basis_points' => 'integer',
            'reservation_payment_deadline_hours' => 'integer',
            'final_payment_days_before_event' => 'integer',
            'customer_payment_upload_cutoff_days' => 'integer',
            'menu_finalization_days_after_acceptance' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @return array<string, mixed> */
    public function quotationSnapshot(): array
    {
        return [
            'schema' => 2,
            'policy_key' => $this->key,
            'quotation_validity_days' => $this->quotation_validity_days,
            'reservation_payment' => [
                'type' => $this->reservation_payment_type,
                'amount_minor' => $this->reservation_payment_amount,
                'basis_points' => $this->reservation_payment_basis_points,
                'payment_deadline_hours' => $this->reservation_payment_deadline_hours,
            ],
            'final_payment_days_before_event' => $this->final_payment_days_before_event,
            'customer_payment_upload_cutoff_days' => $this->customer_payment_upload_cutoff_days,
            'menu_finalization' => [
                'days_after_acceptance' => $this->menu_finalization_days_after_acceptance,
            ],
        ];
    }

    /** @return array<string, int|null> */
    public function confirmationCapacitySnapshot(): array
    {
        return [
            'daily_confirmed_event_capacity' => $this->daily_confirmed_event_capacity,
            'daily_guest_capacity' => $this->daily_guest_capacity,
        ];
    }

    /** @return array<string, int|string> */
    public function cancellationSnapshot(): array
    {
        return [
            'schema' => 1,
            'cancellation_notice_days' => $this->cancellation_notice_days,
            'cancellation_refund_guidance' => $this->cancellation_refund_guidance,
        ];
    }

    public function reservationPaymentPesos(): ?string
    {
        if ($this->reservation_payment_amount === null) {
            return null;
        }

        $minor = (int) $this->reservation_payment_amount;

        return intdiv($minor, 100).'.'.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }
}
