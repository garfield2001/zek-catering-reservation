<?php

namespace App\Http\Controllers\Admin\Package;

use App\Actions\Package\SaveCompletePackage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Package\SaveCompletePackageRequest;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Queries\Package\PackageEditor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConfigurationController extends Controller
{
    public function create(Request $request, PackageEditor $editor): Response|JsonResponse
    {
        $data = $request->validate(['service' => ['required', 'integer', 'exists:product_families,id']]);

        $props = $editor->data(ProductFamily::findOrFail($data['service']));

        return $request->expectsJson() ? response()->json($props) : Inertia::render('admin/packages/editor', $props);
    }

    public function store(SaveCompletePackageRequest $request, SaveCompletePackage $save): RedirectResponse|JsonResponse
    {
        $product = $save->handle($request->user(), $request->validated());

        return $request->expectsJson() ? response()->json(['id' => $product->id], 201) : $this->saved($product, $request->input('save_action') === 'publish');
    }

    public function update(SaveCompletePackageRequest $request, Product $product, SaveCompletePackage $save): RedirectResponse|JsonResponse
    {
        $product = $save->handle($request->user(), $request->validated(), $product);

        return $request->expectsJson() ? response()->json(['id' => $product->id]) : $this->saved($product, $request->input('save_action') === 'publish');
    }

    private function saved(Product $product, bool $published): RedirectResponse
    {
        return redirect()->route($product->family->behavior_type === 'main_package' ? 'catering.show' : 'packages.show', $product)
            ->with('status', $published ? 'Package saved and published.' : 'Draft saved. The current Published Version is unchanged.');
    }
}
