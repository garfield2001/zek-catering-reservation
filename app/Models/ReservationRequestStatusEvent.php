<?php

namespace App\Models;

use App\Enums\ReservationRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationRequestStatusEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['from_status' => ReservationRequestStatus::class, 'to_status' => ReservationRequestStatus::class, 'created_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<ReservationRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(ReservationRequest::class, 'reservation_request_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
