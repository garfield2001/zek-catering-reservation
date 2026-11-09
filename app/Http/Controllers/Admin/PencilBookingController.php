<?php

namespace App\Http\Controllers\Admin;

use App\Actions\PencilBookings\ClosePencilBooking;
use App\Actions\PencilBookings\ConvertPencilBookingToReservationRequest;
use App\Actions\PencilBookings\SavePencilBooking;
use App\Http\Controllers\Controller;
use App\Http\Requests\PencilBookings\ClosePencilBookingRequest;
use App\Http\Requests\PencilBookings\StorePencilBookingRequest;
use App\Http\Requests\PencilBookings\UpdatePencilBookingRequest;
use App\Http\Requests\ReservationRequest\StoreInternalReservationRequest;
use App\Models\PencilBooking;
use App\Models\ReservationRequest;
use App\Queries\ReservationRequests\InternalRequestCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PencilBookingController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', PencilBooking::class);
        $search = $request->string('search')->toString();
        $needle = '%'.strtolower($search).'%';
        $bookings = PencilBooking::query()->when($search !== '', fn ($q) => $q->where(fn ($query) => $query->whereRaw('LOWER(customer_name) LIKE ?', [$needle])->orWhereRaw('LOWER(customer_mobile) LIKE ?', [$needle])->orWhereRaw('LOWER(customer_email) LIKE ?', [$needle])))->latest('id')->paginate(30)->withQueryString();

        return Inertia::render('admin/pencil-bookings/index', ['bookings' => $bookings, 'search' => $search, 'canManage' => $request->user()->can('manage', PencilBooking::class)]);
    }

    public function store(StorePencilBookingRequest $request, SavePencilBooking $action): RedirectResponse
    {
        $booking = $action->create($request->user(), $request->validated());

        return redirect()->route('pencil-bookings.index')->with('status', "Pencil booking #{$booking->id} was saved. It does not hold capacity.");
    }

    public function show(PencilBooking $pencilBooking, InternalRequestCatalog $catalog): Response
    {
        Gate::authorize('view', $pencilBooking);
        $pencilBooking->load(['events.actor', 'convertedReservationRequest.quotation']);

        $canCreateRequest = request()->user()->can('create', ReservationRequest::class);
        $canConvert = request()->user()->can('manage', PencilBooking::class)
            && $canCreateRequest
            && $pencilBooking->status === 'active';
        $booking = $pencilBooking->toArray();
        $booking['event_date'] = $pencilBooking->getRawOriginal('event_date');
        $booking['follow_up_on'] = $pencilBooking->getRawOriginal('follow_up_on');
        $booking['event_start_time'] = $pencilBooking->event_start_time === null
            ? null
            : substr($pencilBooking->event_start_time, 0, 5);

        return Inertia::render('admin/pencil-bookings/show', [
            'booking' => $booking,
            'canManage' => request()->user()->can('manage', PencilBooking::class),
            'canCreateRequest' => $canCreateRequest,
            'canConvert' => $canConvert,
            'catalog' => $canConvert ? $catalog->all() : null,
            'idempotencyKey' => $canConvert ? (string) str()->uuid() : null,
        ]);
    }

    public function update(UpdatePencilBookingRequest $request, PencilBooking $pencilBooking, SavePencilBooking $action): RedirectResponse
    {
        $action->update($request->user(), $pencilBooking, $request->validated());

        return back()->with('status', 'Pencil booking updated. It still does not hold capacity.');
    }

    public function close(ClosePencilBookingRequest $request, PencilBooking $pencilBooking, ClosePencilBooking $action): RedirectResponse
    {
        $action->handle($request->user(), $pencilBooking, $request->validated('status'), $request->validated('reason'));

        return back()->with('status', 'Pencil booking outcome was recorded.');
    }

    public function convert(StoreInternalReservationRequest $request, PencilBooking $pencilBooking, ConvertPencilBookingToReservationRequest $action): RedirectResponse
    {
        Gate::authorize('manage', PencilBooking::class);
        $data = $request->validated();
        $reservationRequest = $action->handle($request, $request->user(), $pencilBooking, $data, $data['selections']);

        return redirect()->route('reservation-requests.show', $reservationRequest)
            ->with('status', 'Pencil booking converted to a reservation request. It does not confirm or reserve the event date.');
    }
}
