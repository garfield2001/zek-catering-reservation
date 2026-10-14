<?php

namespace App\Http\Controllers\Admin\Package;

use App\Actions\Catalog\EnsurePrimaryService;
use App\Actions\Package\SaveProduct;
use App\Http\Controllers\Controller;
use App\Http\Requests\Package\SaveProductRequest;
use App\Models\FamilyOrderDefault;
use App\Models\PackageVersion;
use App\Models\Product;
use App\Models\ProductFamily;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CateringController extends Controller
{
    public function index(EnsurePrimaryService $primary): Response
    {
        Gate::authorize('viewAny', Product::class);
        $family = $primary->handle();

        return Inertia::render('admin/catering/index', [
            'products' => Product::where('family_id', $family->id)->with(['currentVersion:id,package_product_id,status,version_number,price_basis,base_price_minor,minimum_quantity,total_selection_count', 'versions' => fn ($q) => $q->where('status', 'draft')])
                ->orderBy('sort_order')->orderBy('id')->paginate(20),
            'canManage' => Gate::allows('create', Product::class), 'serviceActive' => $family->active,
            'canPrice' => Gate::allows('price', PackageVersion::class),
            'canHistory' => Gate::allows('history', PackageVersion::class),
            'family' => $family->load('orderDefault'), 'canManageService' => Gate::allows('manage', ProductFamily::class),
            'canDefaults' => Gate::allows('viewAny', FamilyOrderDefault::class),
        ]);
    }

    public function create(): RedirectResponse
    {
        Gate::authorize('create', Product::class);

        return redirect()->route('catering.index');
    }

    public function store(SaveProductRequest $request, SaveProduct $save, EnsurePrimaryService $primary): RedirectResponse
    {
        $product = $save->handle($request->user(), [...$request->validated(), 'family_id' => $primary->handle()->id]);

        return redirect()->route('catering.show', $product)->with('status', 'Package created. Configure its menu and pricing below.');
    }
}
