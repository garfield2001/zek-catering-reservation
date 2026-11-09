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

        return Inertia::render('storefront/packages/show', [
            'offering' => $offering,
            'selectionCount' => count($selection->all($request)),
            'activeMainProductId' => $this->activeMainProductId($request, $selection),
        ]);
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
        $activeMainProductId = $this->activeMainProductId($request, $selection);
        if ($activeMainProductId !== null && $activeMainProductId !== $product->id && ! ($data['replace_main'] ?? false)) {
            return response()->json(['message' => 'Choose whether to replace your current Main Catering package.', 'errors' => ['main_package' => ['Replacing your Main Catering package keeps optional add-ons for review, but replaces its food choices and guest quantity.']]], 422);
        }
        unset($data['replace_main']);
        $item = ['type' => 'package', 'inquiry_role' => 'main', 'product_id' => $product->id, 'version_id' => $product->currentVersion->id, ...$data];
        if ($activeMainProductId !== null && $activeMainProductId !== $product->id) {
            $selection->replaceMain($request, 'package:'.$product->id, $item, $this->mainProductIds($request, $selection));
        } else {
            $selection->put($request, 'package:'.$product->id, $item);
        }

        return response()->json(['preview' => $result, 'selection_count' => count($selection->all($request))]);
    }

    private function activeMainProductId(Request $request, Selection $selection): ?int
    {
        return $this->mainProductIds($request, $selection)[0] ?? null;
    }

    /** @return list<int> */
    private function mainProductIds(Request $request, Selection $selection): array
    {
        $ids = collect($selection->all($request))
            ->where('type', 'package')
            ->pluck('product_id')
            ->filter(fn ($id) => is_int($id) || ctype_digit((string) $id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return Product::query()
            ->whereIn('id', $ids)
            ->whereHas('family', fn ($query) => $query->where('behavior_type', 'main_package'))
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }
}
