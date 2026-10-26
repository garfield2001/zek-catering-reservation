<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\PreviewListingRequest;
use App\Http\Requests\Storefront\PreviewPackageRequest;
use App\Models\IndividualListing;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Queries\Storefront\PublicOfferingDirectory;
use App\Services\Catalog\ListingConfiguration;
use App\Services\Catalog\PackagePricePreview;
use App\Services\Storefront\Selection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    public function index(Request $request, PublicOfferingDirectory $directory, Selection $selection): Response
    {
        return Inertia::render('storefront/services/index', ['services' => $directory->services(), 'selectionCount' => count($selection->all($request))]);
    }

    public function show(Request $request, ProductFamily $family, PublicOfferingDirectory $directory, Selection $selection): Response
    {
        abort_unless($family->is_addon && $family->active && $family->archived_at === null, 404);

        return Inertia::render('storefront/services/show', [
            'service' => ['id' => $family->id, 'name' => $family->name, 'description' => $family->description],
            'packages' => $directory->packagesFor($family),
            'listings' => $directory->listingsFor($family),
            'selectionCount' => count($selection->all($request)),
        ]);
    }

    public function packagePreview(PreviewPackageRequest $request, ProductFamily $family, Product $product, PublicOfferingDirectory $directory, PackagePricePreview $preview): JsonResponse
    {
        abort_unless($product->family_id === $family->id, 404);
        abort_unless($directory->packagesFor($family)->contains('id', $product->id), 404);
        $data = $request->validated();

        return response()->json($preview->calculatePublished($product->currentVersion, $data['quantity'], $data['groups']));
    }

    public function packageSelect(PreviewPackageRequest $request, ProductFamily $family, Product $product, PublicOfferingDirectory $directory, PackagePricePreview $preview, Selection $selection): JsonResponse
    {
        abort_unless($product->family_id === $family->id, 404);
        abort_unless($directory->packagesFor($family)->contains('id', $product->id), 404);
        $data = $request->validated();
        $result = $preview->calculatePublished($product->currentVersion, $data['quantity'], $data['groups']);
        $selection->put($request, 'package:'.$product->id, ['type' => 'package', 'product_id' => $product->id, 'version_id' => $product->currentVersion->id, ...$data]);

        return response()->json(['preview' => $result, 'selection_count' => count($selection->all($request))]);
    }

    public function listingPreview(PreviewListingRequest $request, ProductFamily $family, IndividualListing $listing, PublicOfferingDirectory $directory, ListingConfiguration $configuration): JsonResponse
    {
        abort_unless($listing->family_id === $family->id && $directory->listingsFor($family)->contains('id', $listing->id), 404);
        $data = $request->validated();

        return response()->json($configuration->preview($listing, $listing->currentVersion, $data['quantity'], $data['selling_option_id'] ?? null));
    }

    public function listingSelect(PreviewListingRequest $request, ProductFamily $family, IndividualListing $listing, PublicOfferingDirectory $directory, ListingConfiguration $configuration, Selection $selection): JsonResponse
    {
        abort_unless($listing->family_id === $family->id && $directory->listingsFor($family)->contains('id', $listing->id), 404);
        $data = $request->validated();
        $result = $configuration->preview($listing, $listing->currentVersion, $data['quantity'], $data['selling_option_id'] ?? null);
        $key = 'listing:'.$listing->id.':'.($data['selling_option_id'] ?? 'base');
        $selection->put($request, $key, ['type' => 'listing', 'listing_id' => $listing->id, 'version_id' => $listing->currentVersion->id, ...$data]);

        return response()->json(['preview' => $result, 'selection_count' => count($selection->all($request))]);
    }
}
