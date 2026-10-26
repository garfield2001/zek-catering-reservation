<?php

namespace App\Queries\Storefront;

use App\Models\Dish;
use App\Models\IndividualListing;
use App\Models\PackageVersion;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Services\Catalog\ListingConfiguration;
use App\Services\Package\EffectivePackageAvailability;
use App\Services\Package\PublishedSnapshot;
use Illuminate\Support\Collection;

/** Read-only public projection. Published snapshots provide commercial facts; live checks provide availability. */
class PublicOfferingDirectory
{
    public function __construct(
        private EffectivePackageAvailability $packages,
        private ListingConfiguration $listings,
    ) {}

    /** @return Collection<int, array<string, mixed>> */
    public function catering(): Collection
    {
        $family = ProductFamily::query()
            ->where('behavior_type', 'main_package')
            ->where('active', true)
            ->whereNull('archived_at')
            ->first();

        return $family ? $this->packagesFor($family) : collect();
    }

    public function services(): Collection
    {
        return ProductFamily::query()->where('is_addon', true)->where('active', true)->whereNull('archived_at')
            ->orderBy('sort_order')->orderBy('id')->get()
            ->map(function (ProductFamily $family): ?array {
                $packages = $this->packagesFor($family);
                $listings = $this->listingsFor($family);

                if ($packages->isEmpty() && $listings->isEmpty()) {
                    return null;
                }

                return [
                    'id' => $family->id,
                    'name' => $family->name,
                    'description' => $family->description,
                    'behavior' => $family->behavior_type,
                    'packages_count' => $packages->count(),
                    'listings_count' => $listings->count(),
                ];
            })->filter()->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    public function packagesFor(ProductFamily $family): Collection
    {
        return Product::query()->where('family_id', $family->id)->where('active', true)->whereNull('archived_at')
            ->with(['family', 'currentVersion'])->orderBy('sort_order')->orderBy('id')->get()
            ->filter(fn (Product $product) => $product->currentVersion?->status === 'published' && $this->packages->forProduct($product)['available'])
            ->map(fn (Product $product) => $this->package($product))->values();
    }

    /** @return array<string, mixed> */
    public function package(Product $product): array
    {
        $product->loadMissing('family', 'currentVersion');
        /** @var PackageVersion $version */
        $version = $product->currentVersion;

        return $this->projectPackage($product, $version, $version->snapshot());
    }

    /** Authorized administration-only draft presentation projection. */
    public function draftPackage(Product $product, PackageVersion $version): array
    {
        return $this->projectPackage($product, $version, app(PublishedSnapshot::class)->capture($product, $version));
    }

    /** @param array<string, mixed> $snapshot */
    private function projectPackage(Product $product, PackageVersion $version, array $snapshot): array
    {
        $dishIds = collect($snapshot['menu']['food_items'] ?? [])->pluck('id')
            ->merge(collect($snapshot['groups'] ?? [])->pluck('options')->flatten(1)->pluck('food_item.id'))->filter()->unique()->values();
        $images = Dish::whereIn('id', $dishIds)->get()->keyBy('id');
        $image = fn (array $item) => $images->get($item['id'])?->thumbnail_url;
        $menu = $snapshot['menu'];
        if (is_array($menu)) {
            $menu['food_items'] = array_map(fn (array $item) => [...$item, 'thumbnail_url' => $image($item)], $menu['food_items']);
        }

        return [
            'id' => $product->id,
            'name' => $snapshot['product']['name'],
            'description' => $snapshot['product']['description'],
            'family' => $snapshot['collection'],
            'version_id' => $version->id,
            'terms' => $snapshot['version'],
            'menu' => $menu,
            'groups' => $snapshot['groups'],
            'presentation' => $snapshot['presentation'],
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    public function listingsFor(ProductFamily $family): Collection
    {
        return IndividualListing::query()->where('family_id', $family->id)->where('active', true)->whereNull('archived_at')
            ->with(['dish', 'currentVersion'])->orderBy('id')->get()
            ->filter(fn (IndividualListing $listing) => $listing->currentVersion?->status === 'published' && $this->listings->availability($listing)['available'])
            ->map(fn (IndividualListing $listing) => $this->listing($listing))->values();
    }

    /** @return array<string, mixed> */
    public function listing(IndividualListing $listing): array
    {
        $facts = $listing->currentVersion->snapshot();

        return [
            'id' => $listing->id,
            'name' => $facts['name'],
            'description' => $facts['description'],
            'food' => [...$facts['food'], 'thumbnail_url' => $listing->dish->thumbnail_url],
            'category' => $facts['category'],
            'selling_unit' => $facts['selling_unit'],
            'price_minor' => (string) $facts['price_minor'],
            'selling_options' => $facts['selling_options'],
            'minimum_quantity' => $facts['minimum_quantity'],
            'quantity_step' => $facts['quantity_step'],
            'maximum_quantity' => $facts['maximum_quantity'],
        ];
    }
}
