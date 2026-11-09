<?php

namespace App\Queries\Reservations;

use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\Reservation;
use App\Queries\Storefront\PublicOfferingDirectory;

/** Resolves current purchasable offerings for a proposed reservation change. */
class ReservationChangeOfferings
{
    public function __construct(private PublicOfferingDirectory $offerings) {}

    /** @return array<string, mixed>|null */
    public function mainFor(Reservation $reservation): ?array
    {
        $reservation->loadMissing('confirmedQuotationVersion');
        $version = $reservation->confirmedQuotationVersion;
        if ($version === null) {
            return null;
        }

        $mainLine = collect($version->line_snapshots)->firstWhere('line_type', 'main_package');
        $productId = is_array($mainLine)
            ? data_get($mainLine, 'commercial_snapshot.published_package.product.id')
            : null;
        if (! is_int($productId) && ! ctype_digit((string) $productId)) {
            return null;
        }

        $product = Product::query()->with('family')->find((int) $productId);
        if ($product === null || $product->family->behavior_type !== 'main_package') {
            return null;
        }

        $offering = $this->offerings->catering()->firstWhere('id', $product->id);
        if ($offering === null) {
            return null;
        }

        return [
            'offering' => $offering,
            'quantity' => is_numeric(data_get($mainLine, 'quantity')) ? (int) data_get($mainLine, 'quantity') : null,
            'groups' => data_get($mainLine, 'commercial_snapshot.customer_selection.groups', []),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function addons(): array
    {
        return ProductFamily::query()
            ->where('is_addon', true)
            ->where('active', true)
            ->whereNull('archived_at')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (ProductFamily $family): array => [
                'id' => $family->id,
                'name' => $family->name,
                'packages' => $this->offerings->packagesFor($family)->all(),
                'listings' => $this->offerings->listingsFor($family)->all(),
            ])
            ->filter(fn (array $service): bool => $service['packages'] !== [] || $service['listings'] !== [])
            ->values()
            ->all();
    }
}
