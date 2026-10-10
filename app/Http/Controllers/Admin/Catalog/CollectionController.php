<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Actions\Catalog\ArchiveCollection;
use App\Actions\Catalog\CreateCollection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\CreateCollectionRequest;
use App\Models\ProductFamily;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CollectionController extends Controller
{
    public function create(): Response
    {
        Gate::authorize('manage', ProductFamily::class);

        return Inertia::render('admin/catalog/families/create', [
            'hasPrimary' => ProductFamily::where('is_addon', false)->whereNull('archived_at')->exists(),
        ]);
    }

    public function store(CreateCollectionRequest $request, CreateCollection $action): RedirectResponse
    {
        $action->handle($request->user(), $request->validated());

        return redirect()->route('catalog.families.index')->with('status', 'Collection created. Configure its catalog and draft products next.');
    }

    public function archive(Request $request, ProductFamily $family, ArchiveCollection $action): RedirectResponse
    {
        Gate::authorize('manage', ProductFamily::class);
        $action->handle($request->user(), $family);

        return redirect()->route('catalog.families.index')->with('status', 'Collection archived. Existing history and catalog references were retained.');
    }
}
