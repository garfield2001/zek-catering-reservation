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
        return ['active' => 'boolean', 'sort_order' => 'integer', 'archived_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<ProductFamily, $this> */
    public function family(): BelongsTo
    {
        return $this->belongsTo(ProductFamily::class, 'family_id');
    }

    /** @return HasMany<ProductVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order')->orderBy('id');
    }
}
