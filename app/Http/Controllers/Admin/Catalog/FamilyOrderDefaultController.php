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
        $search = request()->query('q');
        $search = is_string($search) ? mb_substr(trim($search), 0, 100) : '';
        $status = request()->query('status');
        $status = in_array($status, ['current', 'archived', 'all'], true) ? $status : 'current';
        $sort = request()->query('sort') === 'name' ? 'name' : 'display';
        $families = ProductFamily::with('orderDefault')
            ->when($status === 'current', fn ($query) => $query->whereNull('archived_at'))
            ->when($status === 'archived', fn ($query) => $query->whereNotNull('archived_at'))
            ->when($search !== '', fn ($query) => $query->where('name', 'ilike', '%'.addcslashes($search, '%_\\').'%'))
            ->when($sort === 'name', fn ($query) => $query->orderBy('name'))
            ->when($sort === 'display', fn ($query) => $query->orderBy('sort_order'))
            ->orderBy('id')->paginate(20)->withQueryString();

        return Inertia::render('admin/catalog/families/index', [
            'families' => $families,
            'filters' => ['q' => $search, 'status' => $status, 'sort' => $sort],
            'canManagePresentation' => Gate::allows('manage', ProductFamily::class),
            'canManageDefaults' => Gate::allows('viewAny', FamilyOrderDefault::class),
            'hasPrimary' => ProductFamily::where('is_addon', false)->whereNull('archived_at')->exists(),
        ]);
    }

    public function update(UpdateFamilyOrderDefaultRequest $request, ProductFamily $family, UpdateFamilyOrderDefault $action): RedirectResponse
    {
        $action->handle($request->user(), $family->orderDefault()->firstOrFail(), $request->validated());

        return redirect()->route('catalog.families.index')->with('status', 'Ordering defaults updated.');
    }
}
