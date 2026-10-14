<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Actions\Catalog\ChangeListingAvailability;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\ListingAvailabilityRequest;
use App\Models\IndividualListing;
use Illuminate\Http\RedirectResponse;

class ListingAvailabilityController extends Controller
{
    public function __invoke(ListingAvailabilityRequest $request, IndividualListing $listing, ChangeListingAvailability $change): RedirectResponse
    {
        $change->handle($request->user(), $listing, $request->validated('operation'));

        return redirect()->route('listings.show', $listing)->with('status', 'Item availability updated. Publication history is retained.');
    }
}
