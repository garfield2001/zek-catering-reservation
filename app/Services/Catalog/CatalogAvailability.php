<?php

namespace App\Services\Catalog;

use App\Models\Dish;
use App\Models\DishOffering;
use App\Models\OfferingOption;
use App\Models\ProductFamily;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class CatalogAvailability
{
    public function dishEligibleForFamily(Dish $dish, ProductFamily $family): bool
    {
        return $this->eligiblePairs()
            ->where('dishes.id', $dish->id)
            ->where('product_families.id', $family->id)
            ->exists();
    }

    /** @param array<int, int> $ids
     * @return array<int, bool>
     */
    public function eligibleDishIds(ProductFamily $family, array $ids): array
    {
        return $this->eligiblePairs()->where('product_families.id', $family->id)
            ->whereIn('dishes.id', $ids)->pluck('dishes.id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])->all();
    }

    public function offeringAvailable(DishOffering $offering): bool
    {
        return $this->availableOfferings()
            ->where('dish_offerings.id', $offering->id)
            ->exists();
    }

    public function optionAvailable(OfferingOption $option): bool
    {
        return $this->availableOfferings()
            ->join('offering_options', 'offering_options.offering_id', '=', 'dish_offerings.id')
            ->where('offering_options.id', $option->id)
            ->where('offering_options.active', true)
            ->exists();
    }

    /** @param array<int, int> $ids
     * @return array<int, bool>
     */
    public function availableOptionIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->availableOfferings()
            ->join('offering_options', 'offering_options.offering_id', '=', 'dish_offerings.id')
            ->whereIn('offering_options.id', $ids)->where('offering_options.active', true)
            ->pluck('offering_options.id')->mapWithKeys(fn ($id) => [(int) $id => true])->all();
    }

    private function availableOfferings(): Builder
    {
        return $this->eligiblePairs()
            ->join('dish_offerings', function ($join) {
                $join->on('dish_offerings.dish_id', '=', 'dish_family_eligibilities.dish_id')
                    ->on('dish_offerings.family_id', '=', 'dish_family_eligibilities.family_id');
            })
            ->where('dish_offerings.active', true);
    }

    private function eligiblePairs(): Builder
    {
        return DB::table('dish_family_eligibilities')
            ->join('dishes', 'dishes.id', '=', 'dish_family_eligibilities.dish_id')
            ->join('dish_categories', 'dish_categories.id', '=', 'dishes.category_id')
            ->join('product_families', 'product_families.id', '=', 'dish_family_eligibilities.family_id')
            ->where('dish_family_eligibilities.active', true)
            ->where('dishes.active', true)
            ->where('dish_categories.active', true)
            ->where('product_families.active', true)
            ->whereNull('product_families.archived_at');
    }
}
