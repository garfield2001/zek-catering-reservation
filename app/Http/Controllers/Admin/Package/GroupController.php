<?php

namespace App\Http\Controllers\Admin\Package;

use App\Actions\Package\DeleteSelectionGroup;
use App\Actions\Package\SaveSelectionGroup;
use App\Http\Controllers\Controller;
use App\Http\Requests\Package\SaveGroupRequest;
use App\Models\PackageVersion;
use App\Models\Product;
use App\Models\SelectionGroup;
use App\Queries\Catalog\AvailablePackageOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class GroupController extends Controller
{
    public function create(Product $product, PackageVersion $version, AvailablePackageOptions $options): RedirectResponse
    {
        Gate::authorize('create', SelectionGroup::class);

        return $this->editor($product, $version, null, $options);
    }

    public function store(SaveGroupRequest $request, Product $product, PackageVersion $version, SaveSelectionGroup $action): RedirectResponse
    {
        $action->handle($request->user(), $version,
            collect($request->validated())->except(['group_code', 'sort_order'])->all());

        return redirect()->route('packages.show', $product)->with('status', 'Inclusion or choice added.');
    }

    public function edit(Product $product, PackageVersion $version, SelectionGroup $group, AvailablePackageOptions $options): RedirectResponse
    {
        Gate::authorize('update', $group);

        return $this->editor($product, $version, $group, $options);
    }

    public function update(SaveGroupRequest $request, Product $product, PackageVersion $version, SelectionGroup $group, SaveSelectionGroup $action): RedirectResponse
    {
        $action->handle($request->user(), $version, $request->validated(), $group);

        return redirect()->route('packages.show', $product)->with('status', 'Inclusion or choice updated.');
    }

    public function destroy(Request $request, Product $product, PackageVersion $version, SelectionGroup $group, DeleteSelectionGroup $action): RedirectResponse
    {
        Gate::authorize('update', $group);
        $data = $request->validate(['draft_revision' => ['required', 'integer', 'min:1']]);
        $action->handle($request->user(), $group, (int) $data['draft_revision']);

        return redirect()->route('packages.show', $product)->with('status', 'Inclusion or choice removed from the draft.');
    }

    private function editor(Product $product, PackageVersion $version, ?SelectionGroup $group, AvailablePackageOptions $options): RedirectResponse
    {
        abort_if($version->status !== 'draft' || $version->family->behavior_type === 'main_package', 404);

        return redirect()->route('packages.show', $product);
    }
}
