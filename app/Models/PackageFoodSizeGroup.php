<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PackageFoodSizeGroup extends Model
{
    protected $fillable = ['package_version_id', 'dish_id', 'measurement_unit_id'];

    /** @return BelongsTo<MeasurementUnit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(MeasurementUnit::class, 'measurement_unit_id');
    }

    /** @return HasMany<PackageFoodSizeChoice, $this> */
    public function choices(): HasMany
    {
        return $this->hasMany(PackageFoodSizeChoice::class, 'size_group_id')->orderBy('quantity');
    }
}
