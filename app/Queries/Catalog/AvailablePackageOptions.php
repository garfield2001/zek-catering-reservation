<?php

namespace App\Queries\Catalog;

use Illuminate\Support\Facades\DB;

class AvailablePackageOptions
{
    public function forFamily(int $familyId): array
    {
        return DB::table('offering_options as option')
            ->join('dish_offerings as offering', 'offering.id', '=', 'option.offering_id')
            ->join('dishes as dish', 'dish.id', '=', 'offering.dish_id')
            ->join('dish_categories as category', 'category.id', '=', 'dish.category_id')
            ->join('product_families as family', 'family.id', '=', 'offering.family_id')
            ->where('family.id', $familyId)->where('family.active', true)->whereNull('family.archived_at')
            ->where('category.active', true)->where('dish.active', true)->where('offering.active', true)
            ->where('option.active', true)
            ->orderBy('category.name')->orderBy('dish.name')->orderBy('option.label')
            ->get(['option.id', 'option.label', 'option.unit', 'option.quantity', 'option.serving_pax',
                'dish.id as dish_id', 'dish.name as dish_name', 'category.id as category_id',
                'category.name as category_name', 'offering.name as offering_name'])->toArray();
    }
}
