<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IndividualListing extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['creation_token', 'creation_hash'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'archived_at' => 'immutable_datetime', 'family_id' => 'integer', 'dish_id' => 'integer', 'offering_option_id' => 'integer'];
    }

    /** @return BelongsTo<ProductFamily, $this> */
    public function family(): BelongsTo
    {
        return $this->belongsTo(ProductFamily::class, 'family_id');
    }

    /** @return BelongsTo<Dish, $this> */
    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }

    /** @return BelongsTo<OfferingOption, $this> */
    public function servingOption(): BelongsTo
    {
        return $this->belongsTo(OfferingOption::class, 'offering_option_id');
    }

    /** @return BelongsTo<ListingVersion, $this> */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(ListingVersion::class, 'current_version_id');
    }

    /** @return HasMany<ListingVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(ListingVersion::class, 'listing_id')->orderBy('version_number');
    }
}
