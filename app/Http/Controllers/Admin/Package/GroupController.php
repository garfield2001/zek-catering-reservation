<?php

namespace App\Http\Controllers\Admin\Package;

use App\Actions\Package\DeleteSelectionGroup;
use App\Actions\Package\SaveSelectionGroup;
use App\Http\Controllers\Controller;
use App\Http\Requests\Package\SaveGroupRequest;
use App\Models\DishCategory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SelectionGroup;
use App\Models\VariantVersion;
use App\Queries\Catalog\AvailablePackageOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class GroupController extends Controller
{
    public function create(Product $product, ProductVariant $variant, VariantVersion $version, AvailablePackageOptions $options): Response
    {
        Gate::authorize('create', SelectionGroup::class);

        return $this->editor($product, $variant, $version, null, $options);
    }

    public function store(SaveGroupRequest $request, Product $product, ProductVariant $variant, VariantVersion $version, SaveSelectionGroup $action): RedirectResponse
    {
        $action->handle($request->user(), $version, $request->validated());

        return redirect()->route('packages.versions.show', [$product, $variant, $version])->with('status', 'Selection slot added.');
    }

    public function edit(Product $product, ProductVariant $variant, VariantVersion $version, SelectionGroup $group, AvailablePackageOptions $options): Response
    {
        Gate::authorize('update', $group);

        return $this->editor($product, $variant, $version, $group, $options);
    }

    public function update(SaveGroupRequest $request, Product $product, ProductVariant $variant, VariantVersion $version, SelectionGroup $group, SaveSelectionGroup $action): RedirectResponse
    {
        $action->handle($request->user(), $version, $request->validated(), $group);

        return redirect()->route('packages.versions.show', [$product, $variant, $version])->with('status', 'Selection slot updated.');
    }

    public function destroy(Request $request, Product $product, ProductVariant $variant, VariantVersion $version, SelectionGroup $group, DeleteSelectionGroup $action): RedirectResponse
    {
        Gate::authorize('update', $group);
        $action->handle($request->user(), $group);

        return redirect()->route('packages.versions.show', [$product, $variant, $version])->with('status', 'Selection slot removed from the draft.');
    }

    private function editor(Product $product, ProductVariant $variant, VariantVersion $version, ?SelectionGroup $group, AvailablePackageOptions $options): Response
    {
        return Inertia::render('admin/packages/group-edit', [
            'product' => $product->load('family'), 'variant' => $variant, 'version' => $version,
            'group' => $group?->load(['options.offeringOption', 'categoryRules', 'dishRules']),
            'availableOptions' => $options->forFamily($version->family_id),
            'categories' => DishCategory::where('active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'canPrice' => Gate::allows('price', VariantVersion::class),
        ]);
    }
}
