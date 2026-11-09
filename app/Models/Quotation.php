<?php

namespace App\Models;

use App\Enums\QuotationStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property QuotationStatus $status
 * @property int|null $reservation_request_id
 * @property int|null $current_version_id
 * @property CarbonImmutable|null $event_date
 * @property string|null $event_start_time
 */
class Quotation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['status' => QuotationStatus::class, 'event_date' => 'immutable_date', 'source_snapshot' => 'array'];
    }

    /** @return BelongsTo<ReservationRequest, $this> */
    public function reservationRequest(): BelongsTo
    {
        return $this->belongsTo(ReservationRequest::class);
    }

    /** @return BelongsTo<QuotationVersion, $this> */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(QuotationVersion::class, 'current_version_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<QuotationVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(QuotationVersion::class)->orderBy('version_number');
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasOne<Reservation, $this> */
    public function reservation(): HasOne
    {
        return $this->hasOne(Reservation::class);
    }

    /** @return HasMany<ReservationConfirmationEvent, $this> */
    public function reservationConfirmationEvents(): HasMany
    {
        return $this->hasMany(ReservationConfirmationEvent::class)->orderBy('id');
    }

    /** @return HasMany<ReservationChangeRequest, $this> */
    public function reservationChangeRequests(): HasMany
    {
        return $this->hasMany(ReservationChangeRequest::class)->orderBy('id');
    }

    /** @return HasMany<CancellationRequest, $this> */
    public function cancellationRequests(): HasMany
    {
        return $this->hasMany(CancellationRequest::class)->orderBy('id');
    }
}
