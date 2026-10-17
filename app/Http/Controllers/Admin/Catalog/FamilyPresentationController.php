<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Actions\Catalog\UpdateFamilyPresentation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\UpdateFamilyPresentationRequest;
use App\Models\ProductFamily;
use Illuminate\Http\RedirectResponse;

class FamilyPresentationController extends Controller
{
    public function update(UpdateFamilyPresentationRequest $request, ProductFamily $family, UpdateFamilyPresentation $action): RedirectResponse
    {
        $action->handle($request->user(), $family, $request->validated());

        return redirect()->back(fallback: route($family->is_addon ? 'services.show' : 'catering.index', $family->is_addon ? [$family] : []))->with('status', 'Service details saved.');
    }
}
