<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Actions\Catalog\SaveIndividualListing;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\SaveListingRequest;
use App\Models\IndividualListing;
use App\Models\ProductFamily;
use App\Queries\Catalog\ListingWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ListingController extends Controller
{
    public function create(ProductFamily $family, ListingWorkspace $workspace): Response
    {
        return Inertia::render('admin/listings/editor', $workspace->editor($family));
    }

    public function edit(IndividualListing $listing, ListingWorkspace $workspace): Response
    {
        return Inertia::render('admin/listings/editor', $workspace->editor($listing->family, $listing));
    }

    public function show(Request $request, IndividualListing $listing, ListingWorkspace $workspace): Response
    {
        $data = $request->validate(['version' => ['nullable', 'integer', 'min:1']]);

        return Inertia::render('admin/listings/show', $workspace->details($listing, isset($data['version']) ? (int) $data['version'] : null));
    }

    public function store(SaveListingRequest $request, SaveIndividualListing $save): RedirectResponse
    {
        $listing = $save->handle($request->user(), $request->validated());

        return redirect()->route('listings.show', $listing)->with('status', 'Item saved as a Draft. Review and publish separately.');
    }

    public function update(SaveListingRequest $request, IndividualListing $listing, SaveIndividualListing $save): RedirectResponse
    {
        $save->handle($request->user(), $request->validated(), $listing);

        return redirect()->route('listings.show', $listing)->with('status', 'Item Draft saved. Published facts remain unchanged.');
    }
}
