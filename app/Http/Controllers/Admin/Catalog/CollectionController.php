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

class CollectionController extends Controller
{
    public function create(): RedirectResponse
    {
        Gate::authorize('manage', ProductFamily::class);

        return redirect()->route('services.index');
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

        return redirect()->back(fallback: route($family->is_addon ? 'services.index' : 'catering.index'))->with('status', 'Service archived. Existing history was retained.');
    }
}
