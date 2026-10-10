<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Actions\Catalog\UpdateFamilyOrderDefault;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\UpdateFamilyOrderDefaultRequest;
use App\Models\FamilyOrderDefault;
use App\Models\ProductFamily;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FamilyOrderDefaultController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', ProductFamily::class);

        return Inertia::render('admin/catalog/families/index', [
            'families' => ProductFamily::with('orderDefault')->orderBy('sort_order')->orderBy('id')->get(),
            'canManagePresentation' => Gate::allows('manage', ProductFamily::class),
            'canManageDefaults' => Gate::allows('viewAny', FamilyOrderDefault::class),
        ]);
    }

    public function update(UpdateFamilyOrderDefaultRequest $request, ProductFamily $family, UpdateFamilyOrderDefault $action): RedirectResponse
    {
        $action->handle($request->user(), $family->orderDefault()->firstOrFail(), $request->validated());

        return redirect()->route('catalog.families.index')->with('status', 'Ordering defaults updated.');
    }
}
