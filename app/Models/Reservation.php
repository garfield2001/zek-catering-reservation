<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property ReservationStatus $status
 * @property CarbonImmutable $event_date
 * @property int|null $guest_count
 * @property int $confirmed_quotation_version_id
 * @property CarbonImmutable $confirmed_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $cancelled_at
 * @property string|null $event_start_time
 * @property string|null $venue_information
 * @property array<string, mixed> $confirmation_snapshot
 * @property array<string, mixed> $capacity_snapshot
 */
class Reservation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'event_date' => 'immutable_date',
            'guest_count' => 'integer',
            'status' => ReservationStatus::class,
            'confirmation_snapshot' => 'array',
            'capacity_snapshot' => 'array',
            'confirmed_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Quotation, $this> */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /** @return BelongsTo<QuotationVersion, $this> */
    public function confirmedQuotationVersion(): BelongsTo
    {
        return $this->belongsTo(QuotationVersion::class, 'confirmed_quotation_version_id');
    }

    /** @return BelongsTo<User, $this> */
    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /** @return HasMany<ReservationStatusEvent, $this> */
    public function statusEvents(): HasMany
    {
        return $this->hasMany(ReservationStatusEvent::class)->orderBy('id');
    }

    /** @return HasMany<ReservationChangeRequest, $this> */
    public function changeRequests(): HasMany
    {
        return $this->hasMany(ReservationChangeRequest::class)->orderBy('id');
    }

    /** @return HasMany<CancellationRequest, $this> */
    public function cancellationRequests(): HasMany
    {
        return $this->hasMany(CancellationRequest::class)->orderBy('id');
    }
}
