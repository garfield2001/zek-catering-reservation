<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $line_number
 * @property string $line_type
 * @property int $quantity
 * @property string $estimated_total_minor
 * @property array<string, mixed> $commercial_snapshot
 */
class ReservationRequestLine extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'estimated_total_minor' => 'string', 'commercial_snapshot' => 'array'];
    }

    /** @return BelongsTo<ReservationRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(ReservationRequest::class, 'reservation_request_id');
    }

    /** @return BelongsTo<PackageVersion, $this> */
    public function packageVersion(): BelongsTo
    {
        return $this->belongsTo(PackageVersion::class);
    }

    /** @return BelongsTo<ListingVersion, $this> */
    public function listingVersion(): BelongsTo
    {
        return $this->belongsTo(ListingVersion::class);
    }
}
