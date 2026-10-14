<?php

namespace App\Http\Controllers\Admin\Package;

use App\Actions\Package\SavePackageVersion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Package\SavePackageVersionRequest;
use App\Models\PackageVersion;
use App\Models\Product;
use App\Services\Catalog\PackageQuantityRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class VersionController extends Controller
{
    public function show(Product $product, PackageVersion $version, PackageQuantityRules $quantities): Response|RedirectResponse
    {
        Gate::authorize('view', $version);
        abort_if($version->package_product_id !== $product->id, 404);
        if ($version->status !== 'draft') {
            Gate::authorize('history', PackageVersion::class);
        } else {
            return redirect()->route($product->family->behavior_type === 'main_package' ? 'catering.show' : 'packages.show', $product);
        }

        return Inertia::render('admin/packages/version-show', [
            'product' => $product->load('family'), 'version' => $version->load(['groups.options.offeringOption.offering.dish', 'groups.categoryRules.category', 'groups.dishRules.dish', 'foodItems.category', 'requiredCategories']),
            'canManage' => $version->status === 'draft' && Gate::allows('update', $version), 'canPrice' => Gate::allows('price', PackageVersion::class),
            'canPublish' => $version->status === 'draft' && Gate::allows('publish', PackageVersion::class),
            'canRevise' => $version->status === 'published' && Gate::allows('update', $version) && Gate::allows('history', PackageVersion::class),
        ]);
    }

    public function edit(Product $product, PackageVersion $version): RedirectResponse
    {
        Gate::authorize('update', $version);
        Gate::authorize('price', PackageVersion::class);
        abort_if($version->status !== 'draft' || $version->package_product_id !== $product->id, 404);

        return redirect()->route($product->family->behavior_type === 'main_package' ? 'catering.show' : 'packages.show', $product)->withFragment('pricing');
    }

    public function update(SavePackageVersionRequest $request, Product $product, PackageVersion $version, SavePackageVersion $action): RedirectResponse
    {
        $action->handle($request->user(), $product, $request->validated(), $version);

        return redirect()->route($product->family->behavior_type === 'main_package' ? 'catering.show' : 'packages.show', $product)->with('status', 'Pricing and quantity requirements saved.');
    }
}
