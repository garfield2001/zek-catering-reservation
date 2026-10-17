<?php

namespace App\Queries\Package;

use App\Models\Dish;
use App\Models\DishCategory;
use App\Models\MeasurementUnit;
use App\Models\PackageVersion;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Services\Package\PackageFoodSizes;
use App\Services\Package\PackageServingDefaults;
use Illuminate\Support\Facades\Gate;

class PackageEditor
{
    public function data(ProductFamily $family, ?Product $product = null): array
    {
        abort_if($family->behavior_type === 'direct_sale' || $family->archived_at || $product?->archived_at, 404);
        Gate::authorize($product ? 'update' : 'create', $product ?? Product::class);
        if (! $product) {
            Gate::authorize('price', PackageVersion::class);
            abort_unless($family->active, 422, 'Enable the service before creating a package.');
        }
        $version = $product?->versions()->where('status', 'draft')->first()
            ?? $product?->currentVersion;
        if ($version?->status === 'published') {
            Gate::authorize('history', PackageVersion::class);
        }
        $version?->load(['foodItems.category', 'requiredCategories']);

        return ['family' => $family, 'product' => $product, 'version' => $version,
            'measurementUnits' => MeasurementUnit::where('active', true)->orderBy('name')->get(['id', 'name', 'abbreviation', 'active']),
            'servingDefault' => $version ? app(PackageServingDefaults::class)->input($version) : null,
            'foodSizes' => $version ? app(PackageFoodSizes::class)->input($version) : (object) [],
            'canPrice' => Gate::allows('price', PackageVersion::class),
            'canPublish' => Gate::allows('publish', PackageVersion::class),
            'canCreateFood' => Gate::allows('create', Dish::class),
            'canCreateUnit' => Gate::allows('create', MeasurementUnit::class),
            'quantitySources' => Gate::allows('price', PackageVersion::class) ? PackageVersion::where('family_id', $family->id)->when($product, fn ($q) => $q->where('package_product_id', '!=', $product->id))->whereIn('status', ['draft', 'published'])->whereHas('product', fn ($q) => $q->whereNull('archived_at'))->with('product:id,name')->orderByDesc('version_number')->get(['id', 'package_product_id', 'version_number', 'minimum_quantity', 'quantity_step', 'maximum_quantity'])->unique('package_product_id')->values() : [],
            'categories' => DishCategory::where('active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'foods' => Dish::where('active', true)->whereHas('category', fn ($q) => $q->where('active', true))
                ->orderBy('name')->get()];
    }
}
