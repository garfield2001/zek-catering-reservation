<?php

namespace App\Models;

use App\Enums\QuotationVersionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property QuotationVersionStatus $status
 * @property int $quotation_id
 * @property int $version_number
 * @property array<int, array<string, mixed>> $line_snapshots
 * @property string $subtotal_minor
 * @property string $adjustment_minor
 * @property string $total_minor
 * @property string $currency
 * @property CarbonImmutable|null $valid_until
 * @property int|null $terms_version_id
 * @property array<string, mixed>|null $terms_snapshot
 * @property CarbonImmutable|null $issued_at
 * @property CarbonImmutable|null $withdrawn_at
 */
class QuotationVersion extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['public_token_hash'];

    protected function casts(): array
    {
        return [
            'status' => QuotationVersionStatus::class,
            'line_snapshots' => 'array', 'terms_snapshot' => 'array',
            'subtotal_minor' => 'string', 'adjustment_minor' => 'string', 'total_minor' => 'string',
            'valid_until' => 'immutable_datetime', 'issued_at' => 'immutable_datetime', 'withdrawn_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Quotation, $this> */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /** @return BelongsTo<QuotationTermsVersion, $this> */
    public function termsVersion(): BelongsTo
    {
        return $this->belongsTo(QuotationTermsVersion::class, 'terms_version_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /** @return HasMany<QuotationAdjustmentRequest, $this> */
    public function adjustmentRequests(): HasMany
    {
        return $this->hasMany(QuotationAdjustmentRequest::class);
    }

    /** @return HasOne<QuotationAcceptance, $this> */
    public function acceptance(): HasOne
    {
        return $this->hasOne(QuotationAcceptance::class);
    }

    /** @return HasMany<QuotationDeliveryEvent, $this> */
    public function deliveryEvents(): HasMany
    {
        return $this->hasMany(QuotationDeliveryEvent::class);
    }

    /** @return HasMany<PaymentAllocation, $this> */
    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /** @return HasMany<ReservationConfirmationEvent, $this> */
    public function reservationConfirmationEvents(): HasMany
    {
        return $this->hasMany(ReservationConfirmationEvent::class)->orderBy('id');
    }
}
