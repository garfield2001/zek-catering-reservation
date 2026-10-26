<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\PreviewPackageRequest;
use App\Models\Product;
use App\Queries\Storefront\PublicOfferingDirectory;
use App\Services\Catalog\PackagePricePreview;
use App\Services\Storefront\Selection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CateringController extends Controller
{
    public function index(Request $request, PublicOfferingDirectory $directory, Selection $selection): Response
    {
        return Inertia::render('storefront/catering/index', [
            'packages' => $directory->catering(),
            'selectionCount' => count($selection->all($request)),
        ]);
    }

    public function show(Request $request, Product $product, PublicOfferingDirectory $directory, Selection $selection): Response
    {
        abort_unless($product->family()->value('behavior_type') === 'main_package', 404);
        $offering = $directory->catering()->firstWhere('id', $product->id);
        abort_if($offering === null, 404);

        return Inertia::render('storefront/packages/show', ['offering' => $offering, 'selectionCount' => count($selection->all($request))]);
    }

    public function preview(PreviewPackageRequest $request, Product $product, PublicOfferingDirectory $directory, PackagePricePreview $preview): JsonResponse
    {
        $offering = $directory->catering()->firstWhere('id', $product->id);
        abort_if($offering === null, 404);
        $data = $request->validated();

        return response()->json($preview->calculatePublished($product->currentVersion, $data['quantity'], $data['groups']));
    }

    public function select(PreviewPackageRequest $request, Product $product, PublicOfferingDirectory $directory, PackagePricePreview $preview, Selection $selection): JsonResponse
    {
        $offering = $directory->catering()->firstWhere('id', $product->id);
        abort_if($offering === null, 404);
        $data = $request->validated();
        $result = $preview->calculatePublished($product->currentVersion, $data['quantity'], $data['groups']);
        $selection->put($request, 'package:'.$product->id, ['type' => 'package', 'product_id' => $product->id, 'version_id' => $product->currentVersion->id, ...$data]);

        return response()->json(['preview' => $result, 'selection_count' => count($selection->all($request))]);
    }
}
