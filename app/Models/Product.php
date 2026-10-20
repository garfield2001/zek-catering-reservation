<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = ['family_id', 'code', 'name', 'description', 'sort_order', 'active', 'archived_at', 'creation_token'];

    protected function casts(): array
    {
        return ['service_format_owned' => 'boolean', 'active' => 'boolean', 'sort_order' => 'integer', 'archived_at' => 'immutable_datetime'];
    }

    public function usesServiceFormat(): bool
    {
        return $this->getAttribute('service_format_owned') === true;
    }

    /** @return BelongsTo<ProductFamily, $this> */
    public function family(): BelongsTo
    {
        return $this->belongsTo(ProductFamily::class, 'family_id');
    }

    /** @return HasMany<PackageVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(PackageVersion::class, 'package_product_id')->orderBy('version_number')->orderBy('id');
    }

    /** @return BelongsTo<PackageVersion, $this> */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(PackageVersion::class, 'current_version_id');
    }
}
