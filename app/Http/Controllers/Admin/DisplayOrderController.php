<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Catalog\MoveDisplayRecord;
use App\Http\Controllers\Controller;
use App\Models\DishCategory;
use App\Models\PackageVersion;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\SelectionGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DisplayOrderController extends Controller
{
    public function collection(Request $request, ProductFamily $family, MoveDisplayRecord $action): RedirectResponse
    {
        Gate::authorize('manage', ProductFamily::class);
        $data = $request->validate(['direction' => ['required', 'in:up,down']]);
        $action->handle($request->user(), $family, $data['direction']);

        return back();
    }

    public function category(Request $request, DishCategory $category, MoveDisplayRecord $action): RedirectResponse
    {
        Gate::authorize('update', $category);
        $data = $request->validate(['direction' => ['required', 'in:up,down']]);
        $action->handle($request->user(), $category, $data['direction']);

        return back();
    }

    public function product(Request $request, Product $product, MoveDisplayRecord $action): RedirectResponse
    {
        Gate::authorize('update', $product);
        $data = $request->validate(['direction' => ['required', 'in:up,down']]);
        $action->handle($request->user(), $product, $data['direction']);

        return back();
    }

    public function group(Request $request, Product $product, PackageVersion $version,
        SelectionGroup $group, MoveDisplayRecord $action): RedirectResponse
    {
        Gate::authorize('update', $group);
        $data = $request->validate(['direction' => ['required', 'in:up,down'],
            'draft_revision' => ['required', 'integer', 'min:1']]);
        $action->handle($request->user(), $group, $data['direction'], (int) $data['draft_revision']);

        return back();
    }
}
