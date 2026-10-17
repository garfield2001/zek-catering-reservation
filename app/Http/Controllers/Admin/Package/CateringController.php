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
use App\Queries\Package\PackageDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CateringController extends Controller
{
    public function index(Request $request, EnsurePrimaryService $primary, PackageDirectory $directory): Response
    {
        Gate::authorize('viewAny', Product::class);
        $family = $primary->handle();
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:all,active,inactive,archived']]);

        return Inertia::render('admin/catering/index', [
            'products' => $directory->query($family, $filters)->paginate(20)->withQueryString(),
            'filters' => $filters,
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
