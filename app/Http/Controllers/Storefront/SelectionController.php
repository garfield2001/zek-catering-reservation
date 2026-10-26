<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\IndividualListing;
use App\Models\Product;
use App\Queries\Storefront\PublicOfferingDirectory;
use App\Services\Catalog\ListingConfiguration;
use App\Services\Catalog\PackagePricePreview;
use App\Services\Storefront\Selection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SelectionController extends Controller
{
    public function index(Request $request, Selection $selection, PublicOfferingDirectory $directory, PackagePricePreview $packages, ListingConfiguration $listings): Response
    {
        $items = [];
        $total = 0;
        foreach ($selection->all($request) as $key => $stored) {
            try {
                if (($stored['type'] ?? null) === 'package') {
                    $product = Product::find($stored['product_id'] ?? null);
                    $offering = $product ? ($product->family_id ? $directory->packagesFor($product->family)->firstWhere('id', $product->id) : null) : null;
                    if (! $product || ! $offering || $product->current_version_id !== ($stored['version_id'] ?? null)) {
                        throw ValidationException::withMessages(['selection' => 'This package is no longer available.']);
                    }
                    $preview = $packages->calculatePublished($product->currentVersion, $stored['quantity'], $stored['groups']);
                    $entry = ['key' => $key, 'type' => 'package', 'offering' => $offering, 'preview' => $preview];
                    $items[] = $entry;
                    $total += (int) $entry['preview']['total_minor'];
                }
                if (($stored['type'] ?? null) === 'listing') {
                    $listing = IndividualListing::find($stored['listing_id'] ?? null);
                    $offering = $listing ? $directory->listingsFor($listing->family)->firstWhere('id', $listing->id) : null;
                    if (! $listing || ! $offering || $listing->current_version_id !== ($stored['version_id'] ?? null)) {
                        throw ValidationException::withMessages(['selection' => 'This food item is no longer available.']);
                    }
                    $preview = $listings->preview($listing, $listing->currentVersion, $stored['quantity'], $stored['selling_option_id'] ?? null);
                    $entry = ['key' => $key, 'type' => 'listing', 'offering' => $offering, 'preview' => $preview];
                    $items[] = $entry;
                    $total += (int) $entry['preview']['total_minor'];
                }
            } catch (ValidationException $exception) {
                $items[] = ['key' => $key, 'invalid' => true, 'message' => collect($exception->errors())->flatten()->first()];
            }
        }

        return Inertia::render('storefront/selection/index', ['items' => $items, 'totalMinor' => (string) $total, 'selectionCount' => count($items)]);
    }

    public function destroy(Request $request, string $key, Selection $selection): RedirectResponse
    {
        $selection->forget($request, $key);

        return back();
    }
}
