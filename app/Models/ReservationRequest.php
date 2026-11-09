<?php

namespace App\Models;

use App\Enums\ReservationRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property ReservationRequestStatus $status
 * @property Carbon $event_date
 */
class ReservationRequest extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'event_ends_next_day' => 'boolean', 'guest_count' => 'integer', 'estimated_subtotal_minor' => 'string',
            'commercial_snapshot' => 'array', 'status' => ReservationRequestStatus::class,
        ];
    }

    /** @return BelongsTo<PackageVersion, $this> */
    public function mainPackageVersion(): BelongsTo
    {
        return $this->belongsTo(PackageVersion::class, 'main_package_version_id');
    }

    /** @return HasMany<ReservationRequestLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(ReservationRequestLine::class)->orderBy('line_number');
    }

    /** @return HasMany<ReservationRequestStatusEvent, $this> */
    public function statusEvents(): HasMany
    {
        return $this->hasMany(ReservationRequestStatusEvent::class)->orderBy('id');
    }

    /** @return HasOne<Quotation, $this> */
    public function quotation(): HasOne
    {
        return $this->hasOne(Quotation::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasMany<CancellationRequest, $this> */
    public function cancellationRequests(): HasMany
    {
        return $this->hasMany(CancellationRequest::class)->orderBy('id');
    }

    /** @return BelongsTo<PencilBooking, $this> */
    public function pencilBooking(): BelongsTo
    {
        return $this->belongsTo(PencilBooking::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
