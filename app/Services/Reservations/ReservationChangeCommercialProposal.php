<?php

namespace App\Services\Reservations;

use App\Models\IndividualListing;
use App\Models\Product;
use App\Models\Reservation;
use App\Services\Catalog\ListingConfiguration;
use App\Services\Catalog\PackagePricePreview;
use Illuminate\Validation\ValidationException;

/** Reuses published catalog calculators for proposed commercial changes. */
class ReservationChangeCommercialProposal
{
    public function __construct(private PackagePricePreview $packages, private ListingConfiguration $listings) {}

    /** @param array<string, mixed> $data @param array<string, mixed> $proposed @return array<string, mixed> */
    public function validate(Reservation $reservation, array $data, array $proposed): array
    {
        $changes = [];
        if (($data['menu_selection'] ?? null) !== null) {
            $changes['main_package'] = $this->mainPackage($reservation, $data['menu_selection'], (int) $proposed['guest_count']);
        }
        if (($data['addon_selection'] ?? null) !== null) {
            $changes['addons'] = $this->addons($data['addon_selection']);
        }

        return $changes;
    }

    /** @param mixed $selection @return array<string, mixed> */
    private function mainPackage(Reservation $reservation, mixed $selection, int $quantity): array
    {
        if (! is_array($selection) || ! isset($selection['groups']) || ! is_array($selection['groups'])) {
            throw ValidationException::withMessages(['menu_selection' => 'Choose the complete proposed menu using the available food choices.']);
        }
        $current = collect($reservation->confirmedQuotationVersion->line_snapshots)->firstWhere('line_type', 'main_package');
        $productId = is_array($current) ? data_get($current, 'commercial_snapshot.published_package.product.id') : null;
        $product = (is_int($productId) || ctype_digit((string) $productId))
            ? Product::query()->with(['family', 'currentVersion'])->find($productId)
            : null;
        if (! $this->availablePackage($product)) {
            throw ValidationException::withMessages(['menu_selection' => 'The package behind this reservation is unavailable for a new menu choice. An Administrator must resolve the commercial revision.']);
        }
        $preview = $this->packages->calculatePublished($product->currentVersion, $quantity, $selection['groups']);

        return $this->packageLine('main_package', $product->name, $product->currentVersion->snapshot(), $selection['groups'], $preview);
    }

    /** @param mixed $selection @return list<array<string, mixed>> */
    private function addons(mixed $selection): array
    {
        if (! is_array($selection) || ! array_is_list($selection)) {
            throw ValidationException::withMessages(['addon_selection' => 'Choose a complete replacement list of add-on packages or individual items.']);
        }
        $lines = [];
        foreach ($selection as $index => $item) {
            if (! is_array($item)) {
                throw ValidationException::withMessages(["addon_selection.{$index}" => 'Each add-on selection must be valid.']);
            }
            $lines[] = match ($item['type'] ?? null) {
                'package' => $this->addonPackage($item, $index),
                'listing' => $this->addonListing($item, $index),
                default => throw ValidationException::withMessages(["addon_selection.{$index}.type" => 'Choose an available add-on package or individual item.']),
            };
        }

        return $lines;
    }

    /** @param array<string, mixed> $item @return array<string, mixed> */
    private function addonPackage(array $item, int $index): array
    {
        $id = $item['product_id'] ?? null;
        $product = is_int($id) ? Product::query()->with(['family', 'currentVersion'])->find($id) : null;
        $quantity = $item['quantity'] ?? null;
        $groups = $item['groups'] ?? null;
        if ($product?->family?->is_addon !== true || ! $this->availablePackage($product)) {
            throw ValidationException::withMessages(["addon_selection.{$index}" => 'This add-on package is no longer available.']);
        }
        if (! is_int($quantity) || ! is_array($groups)) {
            throw ValidationException::withMessages(["addon_selection.{$index}" => 'Enter a whole quantity and complete the available add-on choices.']);
        }
        $preview = $this->packages->calculatePublished($product->currentVersion, $quantity, $groups);

        return $this->packageLine('addon_package', $product->name, $product->currentVersion->snapshot(), $groups, $preview);
    }

    /** @param array<string, mixed> $item @return array<string, mixed> */
    private function addonListing(array $item, int $index): array
    {
        $id = $item['listing_id'] ?? null;
        $listing = is_int($id) ? IndividualListing::query()->with(['family', 'currentVersion', 'dish.category', 'servingOption.offering'])->find($id) : null;
        $quantity = $item['quantity'] ?? null;
        $option = $item['selling_option_id'] ?? null;
        if ($listing?->family?->is_addon !== true || $listing->currentVersion === null || ! $this->listings->availability($listing)['available']) {
            throw ValidationException::withMessages(["addon_selection.{$index}" => 'This individual add-on item is no longer available.']);
        }
        if (! is_int($quantity) || ($option !== null && ! is_int($option))) {
            throw ValidationException::withMessages(["addon_selection.{$index}" => 'Enter a whole quantity and choose a valid selling option.']);
        }
        $preview = $this->listings->preview($listing, $listing->currentVersion, $quantity, $option);

        return [
            'line_type' => 'listing', 'description' => $listing->currentVersion->name,
            'quantity' => $preview['quantity'], 'total_minor' => $preview['total_minor'],
            'commercial_snapshot' => ['schema' => 1, 'published_listing' => $listing->currentVersion->snapshot(), 'customer_selection' => ['selling_option_id' => $option], 'estimate' => $preview],
        ];
    }

    /** @param array<string, mixed>|null $snapshot @param array<int, mixed> $groups @param array<string, mixed> $preview @return array<string, mixed> */
    private function packageLine(string $type, string $name, ?array $snapshot, array $groups, array $preview): array
    {
        return [
            'line_type' => $type, 'description' => $name,
            'quantity' => $preview['quantity'], 'total_minor' => $preview['total_minor'],
            'commercial_snapshot' => ['schema' => 1, 'published_package' => $snapshot, 'customer_selection' => ['groups' => $groups], 'estimate' => $preview],
        ];
    }

    private function availablePackage(?Product $product): bool
    {
        return $product !== null
            && $product->active
            && $product->archived_at === null
            && $product->family->active
            && $product->family->archived_at === null
            && $product->currentVersion !== null;
    }
}
