<?php

namespace App\Http\Controllers\Storefront;

use App\Actions\ReservationRequest\SubmitReservationRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReservationRequest\StoreReservationRequest;
use App\Models\Product;
use App\Services\Storefront\Selection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReservationRequestController extends Controller
{
    public function create(Request $request, Selection $selection): Response|RedirectResponse
    {
        if ($selection->all($request) === []) {
            return redirect()->route('storefront.selection.index')->with('status', 'Choose a Main Catering package before starting a request.');
        }

        $packageIds = collect($selection->all($request))->where('type', 'package')->pluck('product_id')->filter()->all();
        $hasMain = Product::query()->whereIn('id', $packageIds)->whereHas('family', fn ($query) => $query->where('behavior_type', 'main_package'))->exists();
        if (! $hasMain) {
            return redirect()->route('storefront.catering.index')->with('status', 'Choose one Main Catering package before continuing to event details.');
        }

        return Inertia::render('storefront/reservation-requests/create', [
            'selectionCount' => count($selection->all($request)),
            'idempotencyKey' => (string) str()->uuid(),
        ]);
    }

    public function store(StoreReservationRequest $request, SubmitReservationRequest $submit, Selection $selection): RedirectResponse
    {
        $result = $submit->handle($request, $request->validated());
        $reservationRequest = $result['request'];
        if ($result['created']) {
            $selection->clear($request);
        }
        $existingAcknowledgment = $request->session()->get('reservation_request.acknowledgment');
        $existingCode = is_array($existingAcknowledgment)
            && ($existingAcknowledgment['reference'] ?? null) === $reservationRequest->reference
            && is_string($existingAcknowledgment['access_code'] ?? null)
            ? $existingAcknowledgment['access_code']
            : null;
        $request->session()->put('reservation_request.acknowledgment', [
            'reference' => $reservationRequest->reference,
            // A browser retry with the same idempotency key must not erase the
            // code that was already disclosed to that same session. The action
            // never returns an existing code, so another session cannot learn it.
            'access_code' => $result['access_code'] ?? $existingCode,
        ]);

        return redirect()->route('storefront.reservation-requests.submitted', $reservationRequest->reference);
    }

    public function submitted(Request $request, string $reference): Response
    {
        $acknowledgment = $request->session()->get('reservation_request.acknowledgment');
        $storedReference = is_array($acknowledgment) ? ($acknowledgment['reference'] ?? null) : $acknowledgment;
        abort_unless(is_string($storedReference) && hash_equals($storedReference, $reference), 404);

        return Inertia::render('storefront/reservation-requests/submitted', [
            'reference' => $reference,
            'accessCode' => is_array($acknowledgment) ? ($acknowledgment['access_code'] ?? null) : null,
            'selectionCount' => count(app(Selection::class)->all($request)),
        ]);
    }
}
