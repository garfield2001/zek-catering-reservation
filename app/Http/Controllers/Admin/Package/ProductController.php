<?php

namespace App\Http\Controllers\Admin\Package;

use App\Actions\Package\SaveProduct;
use App\Http\Controllers\Controller;
use App\Http\Requests\Package\SaveProductRequest;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\VariantVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Product::class);

        return Inertia::render('admin/packages/index', [
            'products' => Product::query()->select('products.*')
                ->join('product_families as family', 'family.id', '=', 'products.family_id')
                ->with('family')->withCount('variants')
                ->orderBy('family.sort_order')->orderBy('products.sort_order')->orderBy('products.id')->paginate(20),
            'canManage' => Gate::allows('create', Product::class),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Product::class);

        return Inertia::render('admin/packages/product-edit', ['product' => null,
            'families' => ProductFamily::where('active', true)->whereNull('archived_at')
                ->where('behavior_type', '!=', 'direct_sale')->orderBy('sort_order')->orderBy('id')->get()]);
    }

    public function store(SaveProductRequest $request, SaveProduct $action): RedirectResponse
    {
        $product = $action->handle($request->user(), $request->validated());

        return redirect()->route('packages.show', $product)->with('status', 'Package product created. It is draft-only.');
    }

    public function show(Product $product): Response
    {
        Gate::authorize('view', $product);

        return Inertia::render('admin/packages/product-show', [
            'product' => $product->load(['family', 'variants.versions']),
            'canManage' => Gate::allows('update', $product),
            'canPrice' => Gate::allows('price', VariantVersion::class),
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
