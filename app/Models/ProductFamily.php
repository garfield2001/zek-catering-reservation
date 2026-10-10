<?php

namespace App\Models;

use App\Enums\Catalog\CollectionBehavior;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProductFamily extends Model
{
    protected function casts(): array
    {
        return ['is_addon' => 'boolean', 'active' => 'boolean', 'sort_order' => 'integer',
            'archived_at' => 'immutable_datetime'];
    }

    public function behavior(): CollectionBehavior
    {
        $value = $this->getAttribute('behavior_type');
        if (! is_string($value)) {
            throw new \LogicException('Collection behavior is not configured.');
        }

        return CollectionBehavior::from($value);
    }

    /** @return HasOne<FamilyOrderDefault, $this> */
    public function orderDefault(): HasOne
    {
        return $this->hasOne(FamilyOrderDefault::class, 'family_id');
    }

    public function offerings(): HasMany
    {
        return $this->hasMany(DishOffering::class, 'family_id');
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'family_id');
    }
}
