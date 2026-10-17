<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingSellingOption extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['price_minor' => 'string', 'sort_order' => 'integer'];
    }

    /** @return BelongsTo<ListingVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(ListingVersion::class, 'listing_version_id');
    }
}
