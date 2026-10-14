<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Actions\Catalog\UpdateFamilyOrderDefault;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\UpdateFamilyOrderDefaultRequest;
use App\Models\ProductFamily;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class FamilyOrderDefaultController extends Controller
{
    public function index(): RedirectResponse
    {
        Gate::authorize('viewAny', ProductFamily::class);

        return redirect()->route('services.index');
    }

    public function update(UpdateFamilyOrderDefaultRequest $request, ProductFamily $family, UpdateFamilyOrderDefault $action): RedirectResponse
    {
        $action->handle($request->user(), $family->orderDefault()->firstOrFail(), $request->validated());

        return redirect()->route($family->is_addon ? 'services.show' : 'catering.index', $family->is_addon ? [$family] : [])->with('status', 'Ordering defaults updated.');
    }
}
