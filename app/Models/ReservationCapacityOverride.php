<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CarbonImmutable $event_date
 * @property int $maximum_confirmed_events
 * @property int|null $maximum_guests
 */
class ReservationCapacityOverride extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['event_date' => 'immutable_date', 'maximum_confirmed_events' => 'integer', 'maximum_guests' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
