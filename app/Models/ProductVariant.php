<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    protected $fillable = ['product_id', 'code', 'name', 'description', 'sort_order', 'active', 'archived_at', 'current_version_id'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'sort_order' => 'integer', 'archived_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<VariantVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(VariantVersion::class, 'variant_id');
    }

    /** @return BelongsTo<VariantVersion, $this> */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(VariantVersion::class, 'current_version_id');
    }
}
