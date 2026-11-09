<?php

namespace App\Queries\ReservationRequests;

use App\Models\ProductFamily;
use App\Queries\Storefront\PublicOfferingDirectory;

/** Published, currently selectable commercial facts for authenticated request intake. */
class InternalRequestCatalog
{
    public function __construct(private PublicOfferingDirectory $directory) {}

    /** @return array{main_packages: array<int, array<string, mixed>>, add_on_services: array<int, array<string, mixed>>} */
    public function all(): array
    {
        $services = ProductFamily::query()
            ->where('is_addon', true)
            ->where('active', true)
            ->whereNull('archived_at')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (ProductFamily $family): ?array {
                $packages = $this->directory->packagesFor($family)->values()->all();
                $listings = $this->directory->listingsFor($family)->values()->all();

                if ($packages === [] && $listings === []) {
                    return null;
                }

                return [
                    'id' => $family->id,
                    'name' => $family->name,
                    'description' => $family->description,
                    'packages' => $packages,
                    'listings' => $listings,
                ];
            })
            ->filter()
            ->values()
            ->all();

        return [
            'main_packages' => $this->directory->catering()->values()->all(),
            'add_on_services' => $services,
        ];
    }
}
