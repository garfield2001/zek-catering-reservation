<?php

namespace App\Http\Controllers\Storefront;

use App\Actions\Cancellations\CreateCancellationRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cancellations\StoreCancellationRequest;
use App\Models\ReservationRequest;
use App\Services\ReservationRequest\CustomerProgressAccess;
use Illuminate\Http\RedirectResponse;

class CancellationRequestController extends Controller
{
    public function store(StoreCancellationRequest $request, string $reference, CustomerProgressAccess $access, CreateCancellationRequest $cancellations): RedirectResponse
    {
        $reservationRequest = ReservationRequest::query()->where('reference', $reference)->firstOrFail();
        $access->require($request, $reservationRequest);
        $quotation = $reservationRequest->quotation()->firstOrFail();
        $cancellation = $cancellations->handle(null, $quotation, $request->validated(), true);

        return back()->with('status', "Cancellation request #{$cancellation->id} was submitted for Administrator review. Your reservation or quotation has not changed yet.");
    }
}
