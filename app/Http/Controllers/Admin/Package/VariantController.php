<?php

namespace App\Http\Controllers\Admin\Package;

use App\Actions\Package\SaveVariantDraft;
use App\Http\Controllers\Controller;
use App\Http\Requests\Package\SaveVariantRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantVersion;
use App\Services\Catalog\PackageQuantityRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class VariantController extends Controller
{
    public function create(Product $product): Response
    {
        Gate::authorize('create', ProductVariant::class);
        Gate::authorize('price', VariantVersion::class);

        return Inertia::render('admin/packages/variant-edit', ['product' => $product->load('family'),
            'variant' => null, 'version' => null, 'defaults' => $product->family->orderDefault]);
    }

    public function store(SaveVariantRequest $request, Product $product, SaveVariantDraft $action): RedirectResponse
    {
        $version = $action->handle($request->user(), $product, $request->validated());

        return redirect()->route('packages.versions.show', [$product, $version->variant, $version])
            ->with('status', 'Variant draft created. It is not published or bookable.');
    }

    public function show(Product $product, ProductVariant $variant, VariantVersion $version, PackageQuantityRules $quantities): Response
    {
        Gate::authorize('view', $version);
        $effective = $quantities->resolve($product->family, $version->only(
            'minimum_override', 'step_override', 'maximum_override', 'maximum_override_enabled'
        ));
        $version->forceFill($effective);

        return Inertia::render('admin/packages/version-show', [
            'product' => $product->load('family'), 'variant' => $variant,
            'version' => $version->load(['groups.options.offeringOption.offering.dish', 'groups.categoryRules.category', 'groups.dishRules.dish']),
            'canManage' => Gate::allows('update', $version),
            'canPrice' => Gate::allows('price', VariantVersion::class),
        ]);
    }

    public function edit(Product $product, ProductVariant $variant, VariantVersion $version): Response
    {
        Gate::authorize('update', $version);
        Gate::authorize('price', VariantVersion::class);

        return Inertia::render('admin/packages/variant-edit', ['product' => $product->load('family'),
            'variant' => $variant, 'version' => $version, 'defaults' => $product->family->orderDefault]);
    }

    public function update(SaveVariantRequest $request, Product $product, ProductVariant $variant, VariantVersion $version, SaveVariantDraft $action): RedirectResponse
    {
        $action->handle($request->user(), $product, $request->validated(), $variant, $version);

        return redirect()->route('packages.versions.show', [$product, $variant, $version])->with('status', 'Draft updated.');
    }
}
