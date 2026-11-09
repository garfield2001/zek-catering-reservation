<?php

namespace App\Http\Controllers\Storefront;

use App\Actions\Reservations\AgreeReservationChangeQuotation;
use App\Actions\Reservations\CreateReservationChangeRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reservations\AgreeReservationChangeQuotationRequest;
use App\Http\Requests\Reservations\StoreReservationChangeRequest;
use App\Models\Reservation;
use App\Models\ReservationChangeRequest;
use App\Models\ReservationRequest;
use App\Services\ReservationRequest\CustomerProgressAccess;
use Illuminate\Http\RedirectResponse;

class ReservationChangeRequestController extends Controller
{
    public function store(StoreReservationChangeRequest $request, string $reference, CustomerProgressAccess $access, CreateReservationChangeRequest $changes): RedirectResponse
    {
        $reservationRequest = ReservationRequest::query()->where('reference', $reference)->firstOrFail();
        $access->require($request, $reservationRequest);
        $reservation = Reservation::query()->where('quotation_id', $reservationRequest->quotation()->value('id'))->firstOrFail();
        $change = $changes->handle(null, $reservation, $request->validated(), true);

        return back()->with('status', "Change request #{$change->id} was submitted for Administrator review. Your confirmed reservation has not changed.");
    }

    public function agree(AgreeReservationChangeQuotationRequest $request, string $reference, ReservationChangeRequest $reservationChangeRequest, CustomerProgressAccess $access, AgreeReservationChangeQuotation $agreements): RedirectResponse
    {
        $reservationRequest = ReservationRequest::query()->where('reference', $reference)->firstOrFail();
        $access->require($request, $reservationRequest);
        abort_unless(
            $reservationChangeRequest->quotation()->where('reservation_request_id', $reservationRequest->id)->exists(),
            404,
        );
        $agreements->handle($reservationChangeRequest, $request->validated('accepted_by_name'));

        return back()->with('status', 'You agreed to the revised quotation. An Administrator must still re-confirm payment readiness and event capacity before your confirmed reservation changes.');
    }
}
