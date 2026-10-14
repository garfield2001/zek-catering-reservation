<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Actions\Catalog\CloneListingDraft;
use App\Actions\Catalog\PublishListingVersion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\PublishListingRequest;
use App\Models\IndividualListing;
use App\Models\ListingVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ListingPublicationController extends Controller
{
    public function publish(PublishListingRequest $request, IndividualListing $listing, ListingVersion $version, PublishListingVersion $publish): RedirectResponse
    {
        $publish->handle($request->user(), $listing, $version, $request->integer('draft_revision'));

        return redirect()->route('listings.show', $listing)->with('status', 'Item published. Its saved commercial terms are now immutable.');
    }

    public function revise(Request $request, IndividualListing $listing, ListingVersion $version, CloneListingDraft $clone): RedirectResponse
    {
        $clone->handle($request->user(), $listing, $version);

        return redirect()->route('listings.edit', $listing)->with('status', 'New Draft created from this published revision.');
    }
}
