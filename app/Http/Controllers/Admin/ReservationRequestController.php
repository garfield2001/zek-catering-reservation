<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ReservationRequest\CreateInternalReservationRequest;
use App\Actions\ReservationRequest\ResetCustomerAccessCode;
use App\Actions\ReservationRequest\UpdateReservationRequestStatus;
use App\Enums\ReservationRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReservationRequest\StoreInternalReservationRequest;
use App\Http\Requests\ReservationRequest\UpdateReservationRequestStatus as UpdateStatusRequest;
use App\Models\Quotation;
use App\Models\ReservationRequest;
use App\Queries\ReservationRequests\InternalRequestCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ReservationRequestController extends Controller
{
    public function create(Request $request, InternalRequestCatalog $catalog): Response
    {
        Gate::authorize('create', ReservationRequest::class);

        return Inertia::render('admin/reservation-requests/create', [
            'catalog' => $catalog->all(),
            'idempotencyKey' => (string) str()->uuid(),
        ]);
    }

    public function store(StoreInternalReservationRequest $request, CreateInternalReservationRequest $create): RedirectResponse
    {
        $data = $request->validated();
        $result = $create->handle($request, $request->user(), $data, $data['selections']);

        return redirect()->route('reservation-requests.show', $result['request'])
            ->with('status', $result['created'] ? 'Reservation request created.' : 'The existing reservation request was opened.');
    }

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ReservationRequest::class);
        $status = $request->string('status')->toString();
        $requests = ReservationRequest::query()->with('quotation.currentVersion')
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();
        $requests->getCollection()->transform(fn (ReservationRequest $item) => [
            'id' => $item->id,
            'reference' => $item->reference,
            'origin' => $item->origin,
            'status' => $item->status->value,
            'status_label' => $item->status->label(),
            'customer_name' => $item->customer_name,
            'event_date' => $item->event_date->toDateString(),
            'event_start_time' => $item->event_start_time,
            'guest_count' => $item->guest_count,
            'estimated_subtotal_minor' => $item->estimated_subtotal_minor,
            'quotation' => $item->quotation ? ['id' => $item->quotation->id, 'status' => $item->quotation->status->value, 'version_number' => $item->quotation->currentVersion?->version_number] : null,
        ]);

        return Inertia::render('admin/reservation-requests/index', [
            'requests' => $requests,
            'status' => $status,
            'statuses' => array_map(fn ($value) => ['value' => $value->value, 'label' => $value->label()], ReservationRequestStatus::cases()),
            'canCreate' => $request->user()->can('create', ReservationRequest::class),
        ]);
    }

    public function show(ReservationRequest $reservationRequest): Response
    {
        Gate::authorize('view', $reservationRequest);
        $reservationRequest->load(['lines.packageVersion', 'lines.listingVersion', 'statusEvents.actor', 'quotation.currentVersion', 'pencilBooking']);

        return Inertia::render('admin/reservation-requests/show', ['request' => $reservationRequest, 'canManage' => request()->user()->can('update', $reservationRequest), 'canCreateQuotation' => request()->user()->can('create', Quotation::class), 'allowedStatuses' => array_map(fn ($status) => ['value' => $status->value, 'label' => $status->label()], $reservationRequest->status->transitions())]);
    }

    public function update(UpdateStatusRequest $request, ReservationRequest $reservationRequest, UpdateReservationRequestStatus $update): RedirectResponse
    {
        $update->handle($request->user(), $reservationRequest, ReservationRequestStatus::from($request->validated('status')));

        return back()->with('status', 'Request status updated.');
    }

    public function resetCustomerAccessCode(Request $request, ReservationRequest $reservationRequest, ResetCustomerAccessCode $reset): RedirectResponse
    {
        Gate::authorize('update', $reservationRequest);
        $code = $reset->handle($request->user(), $reservationRequest);

        return back()->with('status', 'A new Customer Access Code was generated. Communicate it privately; it will not be shown again.')
            ->with('customer_access_code', $code);
    }
}
