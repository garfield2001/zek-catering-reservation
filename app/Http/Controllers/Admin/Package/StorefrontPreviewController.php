<?php

namespace App\Http\Controllers\Admin\Package;

use App\Http\Controllers\Controller;
use App\Models\PackageVersion;
use App\Models\Product;
use App\Queries\Storefront\PublicOfferingDirectory;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Internal-only presentation review. Draft facts never become public here. */
class StorefrontPreviewController extends Controller
{
    public function __invoke(Product $product, PackageVersion $version, PublicOfferingDirectory $directory): Response
    {
        Gate::authorize('view', $version);
        abort_if($version->package_product_id !== $product->id || $version->status !== 'draft', 404);

        return Inertia::render('admin/packages/storefront-preview', [
            'product' => $product->load('family'),
            'offering' => $directory->draftPackage($product, $version),
            'versionNumber' => $version->version_number,
        ]);
    }
}
