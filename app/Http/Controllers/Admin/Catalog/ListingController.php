<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Actions\Catalog\CreateIndividualListings;
use App\Actions\Catalog\SaveIndividualListing;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\BulkCreateListingsRequest;
use App\Http\Requests\Catalog\SaveListingRequest;
use App\Models\IndividualListing;
use App\Models\ProductFamily;
use App\Queries\Catalog\ListingWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ListingController extends Controller
{
    public function create(Request $request, ProductFamily $family, ListingWorkspace $workspace): Response|JsonResponse
    {
        $data = $workspace->editor($family);

        return $request->expectsJson() ? response()->json($data) : Inertia::render('admin/listings/editor', $data);
    }

    public function bulkCreate(ProductFamily $family, ListingWorkspace $workspace): Response
    {
        return Inertia::render('admin/listings/bulk-editor', $workspace->bulkEditor($family));
    }

    public function bulkStore(BulkCreateListingsRequest $request, ProductFamily $family, CreateIndividualListings $create): RedirectResponse|JsonResponse
    {
        $listings = $create->handle($request->user(), $family, $request->validated('listings'));
        if ($request->expectsJson()) {
            return response()->json(['ids' => array_map(fn (IndividualListing $listing) => $listing->id, $listings)], 201);
        }

        return redirect()->route('services.show', $family)->with('status', count($listings).' individual item Drafts created. Review and publish each item separately.');
    }

    public function edit(Request $request, IndividualListing $listing, ListingWorkspace $workspace): Response|JsonResponse
    {
        $data = $workspace->editor($listing->family, $listing);

        return $request->expectsJson() ? response()->json($data) : Inertia::render('admin/listings/editor', $data);
    }

    public function show(Request $request, IndividualListing $listing, ListingWorkspace $workspace): Response
    {
        $data = $request->validate(['version' => ['nullable', 'integer', 'min:1']]);

        return Inertia::render('admin/listings/show', $workspace->details($listing, isset($data['version']) ? (int) $data['version'] : null));
    }

    public function store(SaveListingRequest $request, SaveIndividualListing $save): RedirectResponse|JsonResponse
    {
        $listing = $save->handle($request->user(), $request->validated());
        if ($request->expectsJson()) {
            return response()->json(['id' => $listing->id], 201);
        }

        return redirect()->route('listings.show', $listing)->with('status', 'Item saved as a Draft. Review and publish separately.');
    }

    public function update(SaveListingRequest $request, IndividualListing $listing, SaveIndividualListing $save): RedirectResponse|JsonResponse
    {
        $save->handle($request->user(), $request->validated(), $listing);
        if ($request->expectsJson()) {
            return response()->json(['id' => $listing->id]);
        }

        return redirect()->route('listings.show', $listing)->with('status', 'Item Draft saved. Published facts remain unchanged.');
    }
}
