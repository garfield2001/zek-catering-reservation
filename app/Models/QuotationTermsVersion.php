<?php

namespace App\Models;

use App\Enums\QuotationTermsStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property QuotationTermsStatus $status
 * @property string|null $tax_price_treatment_statement
 * @property string|null $reservation_payment_type
 * @property string|null $reservation_payment_amount
 * @property int|null $reservation_payment_basis_points
 * @property int $payment_deadline_hours
 * @property int $menu_finalization_days
 * @property string|null $menu_finalization_terms
 * @property string|null $cancellation_refund_terms
 * @property string|null $rescheduling_terms
 * @property string|null $customer_conditions
 * @property CarbonImmutable|null $approved_at
 */
class QuotationTermsVersion extends Model
{
    protected $guarded = ['id', 'version_number', 'status', 'approved_by', 'approved_at'];

    protected function casts(): array
    {
        return [
            'status' => QuotationTermsStatus::class,
            'reservation_payment_amount' => 'string',
            'reservation_payment_basis_points' => 'integer',
            'payment_deadline_hours' => 'integer',
            'menu_finalization_days' => 'integer',
            'approved_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return array<string, mixed> */
    public function commercialSnapshot(): array
    {
        return [
            'schema' => 1,
            'terms_version_id' => $this->id,
            'version_number' => $this->version_number,
            'tax_price_treatment_statement' => $this->tax_price_treatment_statement,
            'reservation_payment' => [
                'type' => $this->reservation_payment_type,
                'amount_minor' => $this->reservation_payment_amount,
                'basis_points' => $this->reservation_payment_basis_points,
                'payment_deadline_hours' => $this->payment_deadline_hours,
            ],
            'menu_finalization' => [
                'days_after_acceptance' => $this->menu_finalization_days,
                'terms' => $this->menu_finalization_terms,
            ],
            'cancellation_refund_terms' => $this->cancellation_refund_terms,
            'rescheduling_terms' => $this->rescheduling_terms,
            'customer_conditions' => $this->customer_conditions,
            'approved_at' => $this->approved_at?->toIso8601String(),
        ];
    }
}
