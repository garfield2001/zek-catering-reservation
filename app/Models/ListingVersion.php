<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ListingVersion extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['price_minor' => 'string', 'published_snapshot' => 'array', 'published_at' => 'immutable_datetime', 'has_selling_options' => 'boolean', 'uses_food_name' => 'boolean'];
    }

    /** @return HasMany<ListingSellingOption, $this> */
    public function sellingOptions(): HasMany
    {
        return $this->hasMany(ListingSellingOption::class)->orderBy('sort_order');
    }

    public function snapshot(): ?array
    {
        $snapshot = $this->getAttribute('published_snapshot');
        if ($snapshot !== null && ! is_array($snapshot)) {
            throw new \LogicException('The published listing snapshot is invalid.');
        }

        return $snapshot;
    }

    /** @return BelongsTo<IndividualListing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(IndividualListing::class, 'listing_id');
    }

    /** @return BelongsTo<ListingVersion, $this> */
    public function clonedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'cloned_from_id');
    }
}
