<?php

namespace App\Models;

use App\Enums\RefundStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $cancellation_request_id
 * @property int $payment_id
 * @property string $amount_minor
 * @property RefundStatus $status
 * @property CarbonImmutable $requested_at
 * @property CarbonImmutable|null $reviewed_at
 * @property CarbonImmutable|null $completed_at
 * @property string|null $evidence_disk
 * @property string|null $evidence_path
 * @property CancellationRequest $cancellationRequest
 * @property Payment $payment
 * @property Collection<int, RefundCorrection> $corrections
 */
class Refund extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['evidence_disk', 'evidence_path', 'evidence_sha256'];

    protected $appends = ['has_evidence'];

    protected function casts(): array
    {
        return [
            'status' => RefundStatus::class, 'amount_minor' => 'string',
            'requested_at' => 'immutable_datetime', 'reviewed_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<CancellationRequest, $this> */
    public function cancellationRequest(): BelongsTo
    {
        return $this->belongsTo(CancellationRequest::class);
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /** @return HasMany<RefundEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(RefundEvent::class)->orderBy('id');
    }

    /** @return HasMany<RefundCorrection, $this> */
    public function corrections(): HasMany
    {
        return $this->hasMany(RefundCorrection::class)->orderBy('id');
    }

    public function getHasEvidenceAttribute(): bool
    {
        return $this->evidence_disk !== null && $this->evidence_path !== null;
    }
}
