<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PencilBooking extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['event_date' => 'immutable_date', 'follow_up_on' => 'immutable_date', 'converted_at' => 'immutable_datetime', 'guest_count' => 'integer'];
    }

    /** @return BelongsTo<ReservationRequest, $this> */
    public function convertedReservationRequest(): BelongsTo
    {
        return $this->belongsTo(ReservationRequest::class, 'converted_reservation_request_id');
    }

    /** @return HasMany<PencilBookingEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(PencilBookingEvent::class)->orderBy('id');
    }
}
