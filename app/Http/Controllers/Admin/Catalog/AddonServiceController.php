<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Actions\Catalog\CreateCollection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\CreateCollectionRequest;
use App\Models\FamilyOrderDefault;
use App\Models\IndividualListing;
use App\Models\PackageVersion;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Queries\Package\PackageDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AddonServiceController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ProductFamily::class);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'archived' => ['nullable', 'boolean']]);

        return Inertia::render('admin/services/index', [
            'services' => ProductFamily::where('is_addon', true)->withCount(['products', 'listings'])
                ->when(! ($filters['archived'] ?? false), fn ($q) => $q->whereNull('archived_at'))
                ->when($filters['q'] ?? null, fn ($q, $search) => $q->where('name', 'ilike', '%'.addcslashes($search, '%_\\').'%'))
                ->orderBy('sort_order')->orderBy('id')->paginate(20)->withQueryString(),
            'canManage' => Gate::allows('manage', ProductFamily::class), 'filters' => $filters,
        ]);
    }

    public function store(CreateCollectionRequest $request, CreateCollection $create): RedirectResponse|JsonResponse
    {
        abort_unless($request->validated('kind') === 'addon', 422);
        $family = $create->handle($request->user(), $request->validated());
        if ($request->expectsJson()) {
            return response()->json(['service' => $family->only('id', 'name')], 201);
        }

        return redirect()->route('services.show', $family)->with('status', 'Add-on service created. Set up its foods and packages below.');
    }

    public function show(Request $request, ProductFamily $family, PackageDirectory $directory): Response
    {
        Gate::authorize('viewAny', ProductFamily::class);
        abort_unless($family->is_addon, 404);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:all,active,inactive,archived']]);

        return Inertia::render('admin/services/show', [
            'family' => $family->load('orderDefault'),
            'products' => Gate::allows('viewAny', Product::class) ? $directory->query($family, $filters)->get() : [],
            'filters' => $filters,
            'canManage' => Gate::allows('manage', ProductFamily::class),
            'canDefaults' => Gate::allows('viewAny', FamilyOrderDefault::class),
            'canPackages' => Gate::allows('create', Product::class),
            'canPrice' => Gate::allows('price', PackageVersion::class),
            'canHistory' => Gate::allows('history', PackageVersion::class),
            'canListings' => Gate::allows('create', IndividualListing::class) && Gate::allows('price', IndividualListing::class),
            'listings' => Gate::allows('viewAny', IndividualListing::class) ? $family->listings()->with(['dish', 'currentVersion', 'versions' => fn ($q) => $q->where('status', 'draft')->when(! Gate::allows('create', IndividualListing::class) && ! Gate::allows('publish', IndividualListing::class), fn ($q) => $q->whereRaw('1 = 0'))])->orderBy('id')->get() : [],
            'canCatalog' => Gate::allows('catalog.view') || Gate::allows('catalog.manage'),
            'canArchive' => Gate::allows('manage', ProductFamily::class) && (! $family->products()->exists() || Gate::allows('lifecycle', Product::class))
                && (! $family->listings()->exists() || Gate::allows('lifecycle', IndividualListing::class)),
        ]);
    }
}
