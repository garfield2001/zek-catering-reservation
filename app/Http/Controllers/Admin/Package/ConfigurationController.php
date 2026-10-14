<?php

namespace App\Http\Controllers\Admin\Package;

use App\Actions\Package\SaveCompletePackage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Package\SaveCompletePackageRequest;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Queries\Package\PackageEditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConfigurationController extends Controller
{
    public function create(Request $request, PackageEditor $editor): Response
    {
        $data = $request->validate(['service' => ['required', 'integer', 'exists:product_families,id']]);

        return Inertia::render('admin/packages/editor', $editor->data(ProductFamily::findOrFail($data['service'])));
    }

    public function store(SaveCompletePackageRequest $request, SaveCompletePackage $save): RedirectResponse
    {
        return $this->saved($save->handle($request->user(), $request->validated()));
    }

    public function update(SaveCompletePackageRequest $request, Product $product, SaveCompletePackage $save): RedirectResponse
    {
        return $this->saved($save->handle($request->user(), $request->validated(), $product));
    }

    private function saved(Product $product): RedirectResponse
    {
        return redirect()->route($product->family->behavior_type === 'main_package' ? 'catering.show' : 'packages.show', $product)
            ->with('status', 'Package saved. Review and publish separately when ready.');
    }
}
