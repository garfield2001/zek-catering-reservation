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

        return redirect()->route('catalog.families.index')->with('status', 'Collection details updated.');
    }
}
