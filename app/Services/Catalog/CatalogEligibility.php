<?php

namespace App\Services\Catalog;

use App\Models\Dish;
use App\Models\DishOffering;
use App\Models\ProductFamily;
use Illuminate\Validation\ValidationException;

class CatalogEligibility
{
    public function __construct(private CatalogAvailability $availability) {}

    public function ensureOfferingCanActivate(Dish $dish, ProductFamily $family): void
    {
        if (! $this->availability->dishEligibleForFamily($dish, $family)) {
            throw ValidationException::withMessages(['family_id' => 'This food item is not currently eligible for the selected active collection.']);
        }
    }

    public function ensureOptionCanActivate(DishOffering $offering): void
    {
        if (! $offering->active) {
            throw ValidationException::withMessages(['active' => 'Activate the offering before adding or activating a serving option.']);
        }
        if (! $this->availability->offeringAvailable($offering)) {
            throw ValidationException::withMessages(['family_id' => 'The food item, category, collection and eligibility must be active.']);
        }
    }
}
