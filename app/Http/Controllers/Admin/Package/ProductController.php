<?php

namespace App\Http\Controllers\Admin\Package;

use App\Actions\Package\SaveProduct;
use App\Http\Controllers\Controller;
use App\Http\Requests\Package\SaveProductRequest;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\VariantVersion;
use App\Services\Package\EffectivePackageAvailability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Product::class);

        $familyId = filter_var($request->query('family_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
        $scope = in_array($request->query('scope'), ['primary', 'addon'], true) ? $request->query('scope') : null;

        return Inertia::render('admin/packages/index', [
            'products' => Product::query()->select('products.*')
                ->join('product_families as family', 'family.id', '=', 'products.family_id')
                ->when($familyId, fn ($query) => $query->where('products.family_id', $familyId))
                ->when($scope === 'primary', fn ($query) => $query->where('family.is_addon', false))
                ->when($scope === 'addon', fn ($query) => $query->where('family.is_addon', true))
                ->with('family')->withCount('variants')
                ->orderBy('family.sort_order')->orderBy('products.sort_order')->orderBy('products.id')->paginate(20),
            'canManage' => Gate::allows('create', Product::class),
            'context' => [
                'familyId' => $familyId,
                'scope' => $scope,
                'label' => $familyId ? ProductFamily::find($familyId)?->name : ($scope === 'primary' ? 'Main Catering Packages' : ($scope === 'addon' ? 'Add-on Services' : 'All Catering Packages')),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Product::class);

        $familyId = filter_var($request->query('family_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
        $scope = in_array($request->query('scope'), ['primary', 'addon'], true) ? $request->query('scope') : null;

        return Inertia::render('admin/packages/product-edit', ['product' => null,
            'families' => ProductFamily::where('active', true)->whereNull('archived_at')
                ->where('behavior_type', '!=', 'direct_sale')
                ->when($scope === 'primary', fn ($query) => $query->where('is_addon', false))
                ->when($scope === 'addon', fn ($query) => $query->where('is_addon', true))
                ->orderBy('sort_order')->orderBy('id')->get(),
            'selectedFamilyId' => $familyId,
            'context' => ['familyId' => $familyId, 'scope' => $scope],
        ]);
    }

    public function store(SaveProductRequest $request, SaveProduct $action): RedirectResponse
    {
        $product = $action->handle($request->user(), collect($request->validated())->except(['code', 'sort_order'])->all());
        $version = $product->variants()->firstOrFail()->versions()->firstOrFail();

        return redirect()->route('packages.versions.show', [$product, $version->variant, $version])
            ->with('status', 'Package created with an initial draft. Configure its inclusions and price.');
    }

    public function show(Product $product, EffectivePackageAvailability $availability): Response
    {
        Gate::authorize('view', $product);
        $canHistory = Gate::allows('history', VariantVersion::class);
        $product->load(['family', 'variants.versions', 'variants.currentVersion']);
        $availabilityByVariant = [];
        foreach ($product->variants as $variant) {
            $variant->setRelation('product', $product);
            $availabilityByVariant[$variant->id] = $availability->forVariant($variant);
            $variant->unsetRelation('product');
        }
        if (! $canHistory) {
            foreach ($product->variants as $variant) {
                $variant->setRelation('versions', $variant->versions->where('status', 'draft')->values());
                $variant->unsetRelation('currentVersion');
            }
        }

        return Inertia::render('admin/packages/product-show', [
            'product' => $product,
            'canManage' => Gate::allows('update', $product),
            'canPrice' => Gate::allows('price', VariantVersion::class),
            'canHistory' => $canHistory,
            'canLifecycle' => Gate::allows('lifecycle', $product),
            'availabilityByVariant' => $availabilityByVariant,
        ]);
    }

    public function edit(Product $product): Response
    {
        Gate::authorize('update', $product);

        return Inertia::render('admin/packages/product-edit', ['product' => $product->load('family'), 'families' => []]);
    }

    public function update(SaveProductRequest $request, Product $product, SaveProduct $action): RedirectResponse
    {
        $action->handle($request->user(), $request->validated(), $product);

        return redirect()->route('packages.show', $product)->with('status', 'Package product updated.');
    }
}
