<?php

namespace App\Http\Controllers\Admin\Package;

use App\Actions\Package\SaveProduct;
use App\Http\Controllers\Controller;
use App\Http\Requests\Package\SaveProductRequest;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Queries\Package\PackageEditor;
use App\Queries\Package\PackageWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        Gate::authorize('viewAny', Product::class);

        $familyId = filter_var($request->query('family_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
        $scope = in_array($request->query('scope'), ['primary', 'addon'], true) ? $request->query('scope') : null;
        if ($scope === 'primary' || (! $scope && ! $familyId) || ($familyId && ProductFamily::find($familyId)?->behavior_type === 'main_package')) {
            return redirect()->route('catering.index');
        }
        if ($scope === 'addon' && ! $familyId) {
            return redirect()->route('services.index');
        }

        return redirect()->route('services.show', ProductFamily::findOrFail($familyId));
    }

    public function create(Request $request): RedirectResponse
    {
        Gate::authorize('create', Product::class);
        $familyId = filter_var($request->query('family_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
        $family = $familyId ? ProductFamily::findOrFail($familyId) : null;
        if ($family?->behavior_type === 'main_package' || (! $family && $request->query('scope') !== 'addon')) {
            return redirect()->route('catering.index');
        }

        return $family ? redirect()->route('services.show', $family) : redirect()->route('services.index');
    }

    public function store(SaveProductRequest $request, SaveProduct $action): RedirectResponse
    {
        $product = $action->handle($request->user(), collect($request->validated())->except(['code', 'sort_order'])->all());

        return redirect()->route($product->family->behavior_type === 'main_package' ? 'catering.show' : 'packages.show', $product)
            ->with('status', 'Package created with an initial draft. Configure its inclusions and price.');
    }

    public function show(Product $product, PackageWorkspace $workspace): Response|RedirectResponse
    {
        Gate::authorize('view', $product);
        if ($product->family->behavior_type === 'main_package') {
            return redirect()->route('catering.show', $product);
        }

        return Inertia::render('admin/packages/workspace', $workspace->data($product));
    }

    public function workspace(Product $product, PackageWorkspace $workspace): Response
    {
        Gate::authorize('view', $product);
        abort_unless($product->family->behavior_type === 'main_package', 404);

        return Inertia::render('admin/packages/workspace', $workspace->data($product));
    }

    public function edit(Request $request, Product $product, PackageEditor $editor): Response|JsonResponse
    {
        Gate::authorize('update', $product);

        $props = $editor->data($product->family, $product);

        return $request->expectsJson() ? response()->json($props) : Inertia::render('admin/packages/editor', $props);
    }

    public function update(SaveProductRequest $request, Product $product, SaveProduct $action): RedirectResponse
    {
        $action->handle($request->user(), $request->validated(), $product);

        return redirect()->route($product->family->behavior_type === 'main_package' ? 'catering.show' : 'packages.show', $product)->with('status', 'Package details saved.');
    }
}
