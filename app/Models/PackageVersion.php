<?php

namespace App\Models;

use App\Enums\Catalog\CollectionBehavior;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PackageVersion extends Model
{
    protected $table = 'package_versions';

    protected $fillable = ['package_product_id', 'family_id', 'version_number', 'status', 'draft_revision',
        'base_price_minor', 'currency', 'price_basis', 'selection_policy', 'minimum_quantity', 'quantity_step', 'maximum_quantity',
        'total_selection_count', 'estimated_capacity', 'subgroup_minimum', 'subgroup_step', 'subgroup_maximum', 'created_by',
        'default_serving_quantity', 'default_serving_unit_id', 'default_serving_size_label',
        'cloned_from_id', 'published_snapshot', 'published_at', 'published_by', 'abandoned_at', 'abandoned_by'];

    protected function casts(): array
    {
        return ['default_serving_quantity' => 'decimal:3', 'default_serving_unit_id' => 'integer', 'draft_revision' => 'integer', 'minimum_quantity' => 'integer',
            'quantity_step' => 'integer', 'maximum_quantity' => 'integer', 'total_selection_count' => 'integer', 'estimated_capacity' => 'integer',
            'subgroup_minimum' => 'integer', 'subgroup_step' => 'integer', 'subgroup_maximum' => 'integer',
            'published_snapshot' => 'array', 'published_at' => 'immutable_datetime', 'abandoned_at' => 'immutable_datetime'];
    }

    public function behavior(): CollectionBehavior
    {
        $behavior = $this->family->behavior();

        return $behavior === CollectionBehavior::Package && $this->price_basis === 'per_pack'
            ? CollectionBehavior::PackedMeal : $behavior;
    }

    public function usesFoodMenu(): bool
    {
        return $this->family->behavior_type === 'main_package' || $this->foodItems()->exists();
    }

    public function snapshot(): ?array
    {
        $value = $this->getAttribute('published_snapshot');
        if ($value !== null && ! is_array($value)) {
            throw new \LogicException('The published package snapshot is invalid.');
        }

        return $value;
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'package_product_id');
    }

    /** @return BelongsTo<ProductFamily, $this> */
    public function family(): BelongsTo
    {
        return $this->belongsTo(ProductFamily::class, 'family_id');
    }

    /** @return HasMany<SelectionGroup, $this> */
    public function groups(): HasMany
    {
        return $this->hasMany(SelectionGroup::class, 'package_version_id')->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<PackageFoodSizeGroup, $this> */
    public function sizeGroups(): HasMany
    {
        return $this->hasMany(PackageFoodSizeGroup::class, 'package_version_id')->orderBy('dish_id');
    }

    /** @return BelongsToMany<Dish, $this> */
    public function foodItems(): BelongsToMany
    {
        return $this->belongsToMany(Dish::class, 'package_version_food_items', 'package_version_id', 'dish_id')
            ->withPivot('is_fixed', 'always_include', 'included_quantity', 'included_unit', 'price_adjustment_minor')->withTimestamps()->orderBy('dishes.id');
    }

    /** @return BelongsToMany<DishCategory, $this> */
    public function requiredCategories(): BelongsToMany
    {
        return $this->belongsToMany(DishCategory::class, 'package_version_required_categories', 'package_version_id', 'category_id')
            ->withTimestamps()->orderBy('dish_categories.sort_order')->orderBy('dish_categories.id');
    }
}
