<?php

namespace App\Http\Controllers\Admin\Package;

use App\Http\Controllers\Controller;
use App\Models\PackageVersion;
use App\Models\Product;
use App\Services\Package\EffectivePackageAvailability;
use App\Services\Package\PublishedSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DetailsController extends Controller
{
    public function __invoke(Request $request, Product $product, PublishedSnapshot $snapshots, EffectivePackageAvailability $availability): JsonResponse
    {
        Gate::authorize('view', $product);
        $request->validate(['draft' => ['sometimes', 'boolean']]);
        $version = $request->boolean('draft') ? $product->versions()->where('status', 'draft')->firstOrFail() : $product->currentVersion;
        if (! $version) {
            $version = $product->versions()->where('status', 'draft')->firstOrFail();
        }
        if ($version->status === 'draft') {
            Gate::authorize('update', $product);
        }
        $hasDraft = $product->versions()->where('status', 'draft')->exists();

        return response()->json(['snapshot' => $version->status === 'published' ? $version->published_snapshot : $snapshots->capture($product, $version),
            'status' => $version->status, 'published_at' => $version->published_at, 'version_number' => $version->version_number,
            'active' => $product->active, 'archived' => $product->archived_at !== null, 'availability' => $availability->forProduct($product),
            'has_draft' => $hasDraft,
            'can_edit' => Gate::allows('update', $product) && ! $product->archived_at && ! $product->family->archived_at
                && ($hasDraft || Gate::allows('history', PackageVersion::class)),
        ])->header('Cache-Control', 'private, no-store');
    }
}
