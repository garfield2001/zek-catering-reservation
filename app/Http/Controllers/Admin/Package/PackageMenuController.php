<?php

namespace App\Http\Controllers\Admin\Package;

use App\Actions\Package\SavePackageMenuConfiguration;
use App\Http\Controllers\Controller;
use App\Http\Requests\Package\SavePackageMenuRequest;
use App\Models\PackageVersion;
use App\Models\Product;
use App\Services\Catalog\CatalogAvailability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class PackageMenuController extends Controller
{
    public function edit(Product $product, PackageVersion $version, CatalogAvailability $availability): RedirectResponse
    {
        Gate::authorize('update', $version);
        abort_if($version->package_product_id !== $product->id || $version->status !== 'draft', 404);

        return redirect()->route($product->family->behavior_type === 'main_package' ? 'catering.show' : 'packages.show', $product)->withFragment('menu');
    }

    public function update(SavePackageMenuRequest $request, Product $product, PackageVersion $version, SavePackageMenuConfiguration $action): RedirectResponse
    {
        abort_if($version->package_product_id !== $product->id, 404);
        $action->handle($request->user(), $version, $request->validated());

        return redirect()->route($product->family->behavior_type === 'main_package' ? 'catering.show' : 'packages.show', $product)->with('status', 'Menu saved.');
    }
}
