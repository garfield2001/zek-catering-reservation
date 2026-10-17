<?php

namespace App\Services\Catalog;

use App\Models\DishCategory;
use App\Models\IndividualListing;
use App\Models\ListingVersion;
use Illuminate\Validation\ValidationException;

class ListingConfiguration
{
    public function __construct(private CatalogAvailability $catalog, private MinorMoney $money) {}

    public function sourceAvailable(IndividualListing $listing): bool
    {
        return $this->catalog->dishEligibleForFamily($listing->dish, $listing->family)
            && (! $listing->offering_option_id || ($listing->servingOption->offering->dish_id === $listing->dish_id
                && $listing->servingOption->offering->family_id === $listing->family_id
                && $this->catalog->optionAvailable($listing->servingOption)));
    }

    public function availability(IndividualListing $listing): array
    {
        $reasons = [];
        if ($listing->archived_at) {
            $reasons[] = 'This item is archived.';
        }
        if (! $listing->active) {
            $reasons[] = 'This item is disabled.';
        }
        if (! $listing->current_version_id) {
            $reasons[] = 'Publish a revision before this item can be offered.';
        }
        if ($listing->current_version_id && ! DishCategory::whereKey($listing->currentVersion->snapshot()['category']['id'] ?? null)->where('active', true)->exists()) {
            $reasons[] = 'The published food category is unavailable.';
        }
        if (! $this->sourceAvailable($listing)) {
            $reasons[] = 'Enable the service, Food Item, category and any selected Serving Option.';
        }

        return ['available' => $reasons === [], 'reasons' => $reasons];
    }

    public function snapshot(IndividualListing $listing, ListingVersion $version): array
    {
        return ['schema' => 2, 'listing_id' => $listing->id, 'version_number' => $version->version_number,
            'selling_options' => $version->sellingOptions()->get()->map(fn ($option) => $option->only(['id', 'label', 'price_minor', 'sort_order']))->all(),
            'service' => $listing->family->only('id', 'code', 'name', 'description'),
            'food' => $listing->dish->only('id', 'name', 'description', 'category_id'),
            'category' => $listing->dish->category->only('id', 'name'),
            'serving_option' => $listing->servingOption?->only('id', 'label', 'unit', 'quantity', 'serving_pax', 'serving_claim'),
            ...$version->only('name', 'description', 'selling_unit', 'price_minor', 'currency', 'minimum_quantity', 'quantity_step', 'maximum_quantity')];
    }

    /** Administration-only calculation: never creates a customer transaction. */
    public function preview(IndividualListing $listing, ListingVersion $version, int $quantity, ?int $sellingOptionId = null): array
    {
        abort_unless($version->listing_id === $listing->id, 404);
        $version = $listing->versions()->findOrFail($version->id);
        $facts = $version->status === 'published' ? $version->snapshot() : $this->snapshot($listing, $version);
        if ($quantity < $facts['minimum_quantity'] || ($facts['maximum_quantity'] !== null && $quantity > $facts['maximum_quantity']) || ($quantity - $facts['minimum_quantity']) % $facts['quantity_step'] !== 0) {
            throw ValidationException::withMessages(['quantity' => "{$facts['name']} requires at least {$facts['minimum_quantity']} {$facts['selling_unit']} units, increasing by {$facts['quantity_step']}".($facts['maximum_quantity'] !== null ? ", up to {$facts['maximum_quantity']}." : '.')]);
        }

        $options = collect($facts['selling_options'] ?? []);
        $price = $facts['price_minor'];
        if ($options->isNotEmpty()) {
            $option = $sellingOptionId !== null ? $options->firstWhere('id', $sellingOptionId) : ($options->count() === 1 ? $options->first() : null);
            if ($option === null) {
                throw ValidationException::withMessages(['selling_option_id' => "Choose a selling option for {$facts['name']}."]);
            }
            $price = $option['price_minor'];
        } elseif ($sellingOptionId !== null) {
            throw ValidationException::withMessages(['selling_option_id' => 'This version has no matching selling option.']);
        }

        return ['quantity' => $quantity, 'unit_price_minor' => (string) $price, 'total_minor' => (string) $this->money->multiply($this->money->parse($price), $quantity), 'currency' => 'PHP'];
    }
}
