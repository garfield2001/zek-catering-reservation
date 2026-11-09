<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property PaymentStatus $status
 * @property string|null $amount_minor
 * @property CarbonImmutable|null $paid_at
 * @property CarbonImmutable|null $verified_at
 * @property CarbonImmutable|null $rejected_at
 * @property CarbonImmutable|null $reversed_at
 * @property string|null $transaction_reference
 * @property string|null $rejection_reason
 * @property bool $has_proof
 */
class Payment extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['proof_disk', 'proof_path', 'proof_sha256'];

    protected $appends = ['has_proof'];

    protected function casts(): array
    {
        return ['status' => PaymentStatus::class, 'amount_minor' => 'string', 'paid_at' => 'immutable_datetime', 'verified_at' => 'immutable_datetime', 'rejected_at' => 'immutable_datetime', 'reversed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Quotation, $this> */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /** @return BelongsTo<ReservationRequest, $this> */
    public function reservationRequest(): BelongsTo
    {
        return $this->belongsTo(ReservationRequest::class);
    }

    /** @return BelongsTo<PaymentMethod, $this> */
    public function method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** @return BelongsTo<User, $this> */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /** @return HasOne<PaymentAllocation, $this> */
    public function allocation(): HasOne
    {
        return $this->hasOne(PaymentAllocation::class)->whereNull('superseded_at');
    }

    /** @return HasMany<PaymentAllocation, $this> */
    public function allocationHistory(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /** @return BelongsTo<QuotationVersion, $this> */
    public function submittedQuotationVersion(): BelongsTo
    {
        return $this->belongsTo(QuotationVersion::class, 'submitted_quotation_version_id');
    }

    /** @return HasMany<PaymentEvidenceVersion, $this> */
    public function evidenceVersions(): HasMany
    {
        return $this->hasMany(PaymentEvidenceVersion::class)->orderBy('version_number');
    }

    public function getHasProofAttribute(): bool
    {
        return $this->proof_disk !== null && $this->proof_path !== null;
    }

    /** @return HasMany<Refund, $this> */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class)->orderBy('id');
    }
}
