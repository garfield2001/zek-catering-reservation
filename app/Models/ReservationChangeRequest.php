<?php

namespace App\Models;

use App\Enums\ReservationChangeRequestStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property ReservationChangeRequestStatus $status
 * @property array<int, string> $change_types
 * @property array<string, mixed> $original_snapshot
 * @property array<string, mixed> $proposed_snapshot
 * @property array<string, mixed> $policy_snapshot
 * @property bool $requested_by_customer
 * @property bool $requires_quotation_revision
 * @property CarbonImmutable $requested_at
 * @property CarbonImmutable|null $reviewed_at
 * @property CarbonImmutable|null $applied_at
 * @property int $reservation_id
 * @property int $quotation_id
 * @property int|null $quotation_version_id
 */
class ReservationChangeRequest extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => ReservationChangeRequestStatus::class,
            'change_types' => 'array', 'original_snapshot' => 'array', 'proposed_snapshot' => 'array', 'policy_snapshot' => 'array',
            'requested_by_customer' => 'boolean', 'requires_quotation_revision' => 'boolean',
            'requested_at' => 'immutable_datetime', 'reviewed_at' => 'immutable_datetime', 'applied_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Reservation, $this> */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /** @return BelongsTo<Quotation, $this> */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /** @return BelongsTo<QuotationVersion, $this> */
    public function quotationVersion(): BelongsTo
    {
        return $this->belongsTo(QuotationVersion::class);
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
    public function applier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    /** @return HasMany<ReservationChangeRequestEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(ReservationChangeRequestEvent::class)->orderBy('id');
    }
}
