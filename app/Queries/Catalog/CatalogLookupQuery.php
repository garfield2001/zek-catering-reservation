<?php

namespace App\Queries\Catalog;

use App\Models\Dish;
use App\Models\DishCategory;
use App\Models\ProductFamily;

class CatalogLookupQuery
{
    public function categories(?Dish $dish = null): array
    {
        return DishCategory::where(fn ($query) => $query->where('active', true)
            ->when($dish, fn ($query) => $query->orWhere('id', $dish->category_id)))
            ->orderBy('sort_order')->orderBy('name')->get(['id', 'code', 'name', 'active'])->toArray();
    }

    public function families(): array
    {
        return ProductFamily::where('active', true)->whereNull('archived_at')->orderBy('sort_order')->orderBy('id')
            ->get(['id', 'code', 'name', 'is_addon', 'behavior_type'])->toArray();
    }

    public function eligibleFamilies(Dish $dish): array
    {
        return $dish->eligibleFamilies()->wherePivot('active', true)
            ->where('product_families.active', true)->whereNull('product_families.archived_at')->orderBy('product_families.id')
            ->get(['product_families.id', 'code', 'name', 'is_addon'])->toArray();
    }
}
