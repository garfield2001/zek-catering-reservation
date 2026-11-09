<?php

namespace App\Models;

use App\Enums\CancellationRequestStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $quotation_id
 * @property int|null $reservation_id
 * @property string $kind
 * @property CancellationRequestStatus $status
 * @property array<string, mixed> $policy_snapshot
 * @property array<string, mixed>|null $reservation_snapshot
 * @property array<string, mixed> $financial_snapshot
 * @property CarbonImmutable $requested_at
 * @property CarbonImmutable|null $reviewed_at
 * @property CarbonImmutable|null $finalized_at
 * @property Quotation $quotation
 * @property Reservation|null $reservation
 * @property Collection<int, Refund> $refunds
 */
class CancellationRequest extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => CancellationRequestStatus::class,
            'policy_snapshot' => 'array', 'reservation_snapshot' => 'array', 'financial_snapshot' => 'array',
            'requested_by_customer' => 'boolean', 'requested_by_business' => 'boolean',
            'requested_at' => 'immutable_datetime', 'reviewed_at' => 'immutable_datetime', 'finalized_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<ReservationRequest, $this> */
    public function reservationRequest(): BelongsTo
    {
        return $this->belongsTo(ReservationRequest::class);
    }

    /** @return BelongsTo<Quotation, $this> */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /** @return BelongsTo<Reservation, $this> */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
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
    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    /** @return HasMany<CancellationRequestEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(CancellationRequestEvent::class)->orderBy('id');
    }

    /** @return HasMany<Refund, $this> */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class)->orderBy('id');
    }
}
