<?php

namespace App\Http\Controllers\Admin\Package;

use App\Http\Controllers\Controller;
use App\Http\Requests\Package\PreviewPackageRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantVersion;
use App\Services\Catalog\PackagePricePreview;
use Illuminate\Http\JsonResponse;

class PreviewController extends Controller
{
    public function __invoke(PreviewPackageRequest $request, Product $product, ProductVariant $variant, VariantVersion $version, PackagePricePreview $preview): JsonResponse
    {
        $data = $request->validated();
        $groups = array_map(fn ($row) => ['quantity' => (int) $row['quantity'], 'selections' => $row['selections']], $data['groups']);

        return response()->json($preview->calculate($version, (int) $data['quantity'], $groups));
    }
}
