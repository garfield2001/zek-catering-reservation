<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Reservations\ApplyReservationChangeRequest;
use App\Actions\Reservations\CompleteReservation;
use App\Actions\Reservations\ConfirmReservation;
use App\Actions\Reservations\CreateReservationChangeRequest;
use App\Actions\Reservations\DeleteReservationCapacityOverride;
use App\Actions\Reservations\RecordReservationConfirmationOutcome;
use App\Actions\Reservations\ReviewReservationChangeRequest;
use App\Actions\Reservations\SaveReservationCapacityOverride;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reservations\ApplyReservationChangeRequest as ApplyReservationChangeRequestForm;
use App\Http\Requests\Reservations\CompleteReservationRequest;
use App\Http\Requests\Reservations\ConfirmReservationRequest;
use App\Http\Requests\Reservations\RecordReservationConfirmationOutcomeRequest;
use App\Http\Requests\Reservations\ReviewReservationChangeRequest as ReviewReservationChangeRequestForm;
use App\Http\Requests\Reservations\SaveReservationCapacityOverrideRequest;
use App\Http\Requests\Reservations\StoreReservationChangeRequest;
use App\Models\BusinessPolicy;
use App\Models\CancellationRequest;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\QuotationVersion;
use App\Models\Refund;
use App\Models\RefundCorrection;
use App\Models\Reservation;
use App\Models\ReservationCapacityOverride;
use App\Models\ReservationChangeRequest;
use App\Queries\Reservations\ReservationChangeOfferings;
use App\Services\Payments\PaymentBalance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ReservationController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Reservation::class);
        $quotes = Quotation::query()
            ->with([
                'reservation.confirmedQuotationVersion:id,version_number',
                'versions.acceptance',
                'reservationConfirmationEvents',
            ])
            ->whereHas('versions', fn ($query) => $query->whereHas('acceptance'))
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();
        $quotes->getCollection()->transform(fn (Quotation $quotation) => $this->queueData($quotation));

        return Inertia::render('admin/reservations/index', [
            'quotations' => $quotes,
            'canManageCapacity' => $request->user()->can('manageCapacity', Reservation::class),
        ]);
    }

    public function show(Quotation $quotation, PaymentBalance $balances, ReservationChangeOfferings $changeOfferings): Response
    {
        Gate::authorize('viewAny', Reservation::class);
        $quotation->load([
            'reservation.confirmedQuotationVersion:id,version_number,total_minor,line_snapshots',
            'reservation.confirmer:id,name', 'reservation.completer:id,name', 'reservation.statusEvents.actor:id,name', 'reservation.changeRequests.requester:id,name', 'reservation.changeRequests.reviewer:id,name', 'reservation.changeRequests.applier:id,name', 'reservation.changeRequests.quotationVersion:id,version_number,status,total_minor', 'reservation.changeRequests.events.actor:id,name',
            'reservationRequest:id,reference,guest_count', 'versions.acceptance',
            'reservationConfirmationEvents.quotationVersion:id,version_number', 'reservationConfirmationEvents.recorder:id,name',
            'cancellationRequests.requester:id,name', 'cancellationRequests.reviewer:id,name', 'cancellationRequests.finalizer:id,name',
            'cancellationRequests.refunds.payment:id,amount_minor,status', 'cancellationRequests.refunds.requester:id,name',
            'cancellationRequests.refunds.reviewer:id,name', 'cancellationRequests.refunds.completer:id,name', 'cancellationRequests.refunds.corrections.recorder:id,name',
        ]);
        $latest = $this->latestAcceptedVersion($quotation);
        $eventDate = $quotation->event_date?->toDateString();
        $override = $eventDate ? ReservationCapacityOverride::query()->whereDate('event_date', $eventDate)->first() : null;
        $policy = BusinessPolicy::query()->where('key', 'current')->sole();
        $usage = $eventDate ? Reservation::query()
            ->whereIn('status', [ReservationStatus::Confirmed->value, ReservationStatus::Completed->value])
            ->whereDate('event_date', $eventDate) : null;
        $canViewPayments = request()->user()->can('viewAny', Payment::class);
        $balance = $latest ? $balances->forVersion($latest) : null;

        return Inertia::render('admin/reservations/show', [
            'quotation' => [
                'id' => $quotation->id,
                'reference' => $quotation->reference,
                'customer_name' => $quotation->customer_name,
                'event_date' => $eventDate,
                'event_start_time' => $quotation->event_start_time,
                'venue_information' => $quotation->venue_information,
                'origin' => $quotation->origin,
                'request_guest_count' => $quotation->reservationRequest?->guest_count,
                'latest_accepted_version' => $latest ? ['id' => $latest->id, 'version_number' => $latest->version_number, 'total_minor' => $latest->total_minor, 'accepted_at' => $latest->acceptance?->accepted_at->toIso8601String()] : null,
                'reservation' => $quotation->reservation ? [
                    'id' => $quotation->reservation->id, 'reference' => $quotation->reservation->reference,
                    'status' => $quotation->reservation->status->value,
                    'confirmed_quotation_version_id' => $quotation->reservation->confirmed_quotation_version_id,
                    'confirmed_version_number' => $quotation->reservation->confirmedQuotationVersion->version_number,
                    'event_date' => $quotation->reservation->event_date->toDateString(),
                    'event_start_time' => $quotation->reservation->event_start_time,
                    'venue_information' => $quotation->reservation->venue_information,
                    'guest_count' => $quotation->reservation->guest_count,
                    'confirmed_at' => $quotation->reservation->confirmed_at->toIso8601String(),
                    'confirmed_by' => $quotation->reservation->confirmer->name,
                    'completed_at' => $quotation->reservation->completed_at?->toIso8601String(),
                    'completed_by' => $quotation->reservation->completer?->name,
                    'completion_note' => $quotation->reservation->completion_note,
                    'capacity_snapshot' => $quotation->reservation->capacity_snapshot,
                    'status_events' => $quotation->reservation->statusEvents->map(fn ($event) => [
                        'id' => $event->id, 'from_status' => $event->from_status, 'to_status' => $event->to_status,
                        'note' => $event->note, 'created_at' => $event->created_at->toIso8601String(), 'actor' => $event->actor->name,
                    ])->values(),
                    'change_requests' => $quotation->reservation->changeRequests->map(fn ($change) => [
                        'id' => $change->id, 'status' => $change->status->value, 'change_types' => $change->change_types,
                        'original_snapshot' => $change->original_snapshot, 'proposed_snapshot' => $change->proposed_snapshot,
                        'request_note' => $change->request_note, 'review_note' => $change->review_note, 'exception_reason' => $change->exception_reason,
                        'requires_quotation_revision' => $change->requires_quotation_revision,
                        'quotation_version' => $change->quotationVersion ? ['id' => $change->quotationVersion->id, 'version_number' => $change->quotationVersion->version_number, 'status' => $change->quotationVersion->status->value, 'total_minor' => $change->quotationVersion->total_minor] : null,
                        'requested_at' => $change->requested_at->toIso8601String(), 'reviewed_at' => $change->reviewed_at?->toIso8601String(), 'applied_at' => $change->applied_at?->toIso8601String(),
                        'requester' => $change->requested_by_customer ? 'Customer' : $change->requester?->name,
                        'reviewer' => $change->reviewer?->name, 'applier' => $change->applier?->name,
                    ])->values(),
                ] : null,
            ],
            'paymentReadiness' => $balance ? $this->paymentReadiness($balance, $canViewPayments) : null,
            'capacity' => $eventDate ? [
                'event_date' => $eventDate,
                'maximum_confirmed_events' => $override !== null ? $override->maximum_confirmed_events : $policy->daily_confirmed_event_capacity,
                'maximum_guests' => $override !== null ? $override->maximum_guests : $policy->daily_guest_capacity,
                'confirmed_events' => $usage?->count() ?? 0,
                'confirmed_guests' => (int) ($usage?->sum('guest_count') ?? 0),
                'source' => $override ? 'date override' : 'Business Policies',
            ] : null,
            'confirmationEvents' => $quotation->reservationConfirmationEvents->map(fn ($event) => [
                'id' => $event->id, 'outcome' => $event->outcome, 'reason' => $event->reason,
                'version_number' => $event->quotationVersion->version_number,
                'unresolved_financial_obligations' => (bool) ($event->payment_snapshot['unresolved_financial_obligations'] ?? false),
                'created_at' => $event->created_at->toIso8601String(), 'actor' => $event->recorder->name,
            ])->values(),
            'canConfirm' => request()->user()->can('confirm', Reservation::class),
            'canComplete' => $quotation->reservation && request()->user()->can('complete', $quotation->reservation),
            'canManageCapacity' => request()->user()->can('manageCapacity', Reservation::class),
            'canRequestChange' => $quotation->reservation && request()->user()->can('requestChange', $quotation->reservation),
            'canReviewChange' => $quotation->reservation && request()->user()->can('reviewChange', $quotation->reservation),
            'changeMenuOffering' => $quotation->reservation === null ? null : $changeOfferings->mainFor($quotation->reservation),
            'changeAddonOfferings' => $changeOfferings->addons(),
            'requiresReconfirmation' => $quotation->reservation !== null && $latest !== null && $quotation->reservation->confirmed_quotation_version_id !== $latest->id,
            'cancellations' => $quotation->cancellationRequests->map(fn (CancellationRequest $cancellation) => [
                'id' => $cancellation->id, 'kind' => $cancellation->kind, 'status' => $cancellation->status->value,
                'reason' => $cancellation->reason, 'internal_note' => $cancellation->internal_note,
                'requested_by_customer' => $cancellation->requested_by_customer, 'requested_by_business' => $cancellation->requested_by_business,
                'requested_at' => $cancellation->requested_at->toIso8601String(), 'review_note' => $cancellation->review_note,
                'reviewed_at' => $cancellation->reviewed_at?->toIso8601String(), 'finalized_at' => $cancellation->finalized_at?->toIso8601String(),
                'requester' => $cancellation->requested_by_customer ? 'Customer' : $cancellation->requester?->name,
                'reviewer' => $cancellation->reviewer?->name, 'finalizer' => $cancellation->finalizer?->name,
                'financial_snapshot' => $cancellation->financial_snapshot,
                'refunds' => $cancellation->refunds->map(fn (Refund $refund) => [
                    'id' => $refund->id, 'payment_id' => $refund->payment_id, 'payment_amount_minor' => $refund->payment->amount_minor,
                    'amount_minor' => $refund->amount_minor, 'status' => $refund->status->value, 'reason' => $refund->reason,
                    'internal_note' => $refund->internal_note, 'review_note' => $refund->review_note,
                    'requested_at' => $refund->requested_at->toIso8601String(), 'reviewed_at' => $refund->reviewed_at?->toIso8601String(),
                    'completed_at' => $refund->completed_at?->toIso8601String(), 'has_evidence' => $refund->has_evidence,
                    'corrections' => $refund->corrections->map(fn (RefundCorrection $correction) => ['id' => $correction->id, 'amount_minor' => $correction->amount_minor, 'reason' => $correction->reason, 'created_at' => $correction->created_at->toIso8601String(), 'actor' => $correction->recorder->name])->values(),
                ])->values(),
            ])->values(),
            'verifiedPayments' => $quotation->payments()->where('status', 'verified')->get(['id', 'amount_minor']),
            'canRequestCancellation' => request()->user()->can('request', CancellationRequest::class),
            'canReviewCancellation' => request()->user()->isAdmin(),
        ]);
    }

    public function storeChangeRequest(StoreReservationChangeRequest $request, Reservation $reservation, CreateReservationChangeRequest $action): RedirectResponse
    {
        Gate::authorize('requestChange', $reservation);
        $change = $action->handle($request->user(), $reservation, $request->validated(), false);

        return back()->with('status', "Change request #{$change->id} is awaiting Administrator review.");
    }

    public function reviewChangeRequest(ReviewReservationChangeRequestForm $request, ReservationChangeRequest $reservationChangeRequest, ReviewReservationChangeRequest $action): RedirectResponse
    {
        Gate::authorize('reviewChange', $reservationChangeRequest->reservation);
        $change = $action->handle(
            $request->user(),
            $reservationChangeRequest,
            $request->boolean('approved'),
            (string) $request->validated('review_note'),
            $request->validated('exception_reason'),
        );

        $message = $change->status->value === 'declined'
            ? 'Reservation change request declined. The confirmed reservation remains unchanged.'
            : ($change->requires_quotation_revision
                ? 'Change approved. A quotation revision draft was created; review, issue, and obtain the customer agreement before re-confirmation.'
                : 'Change approved. Re-confirm capacity and payment before applying it to the reservation.');

        return redirect()->route('reservations.show', $change->quotation_id)->with('status', $message);
    }

    public function applyChangeRequest(ApplyReservationChangeRequestForm $request, ReservationChangeRequest $reservationChangeRequest, ApplyReservationChangeRequest $action): RedirectResponse
    {
        Gate::authorize('reviewChange', $reservationChangeRequest->reservation);
        $change = $action->handle($request->user(), $reservationChangeRequest);

        return redirect()->route('reservations.show', $change->quotation_id)->with('status', 'Reservation change applied after payment and capacity re-confirmation.');
    }

    public function confirm(ConfirmReservationRequest $request, Quotation $quotation, ConfirmReservation $action): RedirectResponse
    {
        $reservation = $action->handle($request->user(), $quotation, $request->integer('guest_count') ?: null);

        return redirect()->route('reservations.show', $quotation)->with('status', "Reservation {$reservation->reference} confirmed. Capacity is now committed for the event date.");
    }

    public function recordOutcome(RecordReservationConfirmationOutcomeRequest $request, Quotation $quotation, RecordReservationConfirmationOutcome $action): RedirectResponse
    {
        $event = $action->handle($request->user(), $quotation, $request->validated('outcome'), $request->validated('reason'));

        return back()->with('status', "Confirmation {$event->outcome} decision recorded. Financial history remains unchanged.");
    }

    public function complete(CompleteReservationRequest $request, Reservation $reservation, CompleteReservation $action): RedirectResponse
    {
        $action->handle($request->user(), $reservation, $request->validated('note'));

        return back()->with('status', 'Reservation marked completed. Its confirmation and financial history remain preserved.');
    }

    public function capacity(Request $request): Response
    {
        Gate::authorize('manageCapacity', Reservation::class);

        return Inertia::render('admin/reservations/capacity', [
            'overrides' => ReservationCapacityOverride::query()->with('updatedBy:id,name')->orderBy('event_date')->paginate(30),
        ]);
    }

    public function saveCapacityOverride(SaveReservationCapacityOverrideRequest $request, SaveReservationCapacityOverride $action): RedirectResponse
    {
        $action->handle($request->user(), $request->validated());

        return back()->with('status', 'Date-specific capacity override saved. Existing confirmed and completed reservations keep their recorded capacity snapshot.');
    }

    public function removeCapacityOverride(Request $request, ReservationCapacityOverride $reservationCapacityOverride, DeleteReservationCapacityOverride $action): RedirectResponse
    {
        Gate::authorize('manageCapacity', Reservation::class);
        $action->handle($request->user(), $reservationCapacityOverride);

        return back()->with('status', 'Date-specific capacity override removed. Future checks use the current Business Policies default.');
    }

    /** @return array<string, mixed> */
    private function queueData(Quotation $quotation): array
    {
        $latest = $this->latestAcceptedVersion($quotation);
        $lastEvent = $quotation->reservationConfirmationEvents->last();

        return [
            'id' => $quotation->id, 'reference' => $quotation->reference,
            'customer_name' => $quotation->customer_name, 'event_date' => $quotation->event_date?->toDateString(),
            'event_start_time' => $quotation->event_start_time,
            'latest_accepted_version' => $latest?->version_number,
            'reservation' => $quotation->reservation ? [
                'reference' => $quotation->reservation->reference,
                'status' => $quotation->reservation->status->value,
                'confirmed_version_number' => $quotation->reservation->confirmedQuotationVersion->version_number,
            ] : null,
            'latest_decision' => $lastEvent ? ['outcome' => $lastEvent->outcome, 'version_number' => $lastEvent->quotationVersion->version_number] : null,
            'requires_reconfirmation' => $quotation->reservation !== null && $latest !== null && $quotation->reservation->confirmed_quotation_version_id !== $latest->id,
        ];
    }

    private function latestAcceptedVersion(Quotation $quotation): ?QuotationVersion
    {
        return $quotation->versions->filter(fn ($version) => $version->acceptance !== null)->sortByDesc('version_number')->first();
    }

    /** @param array<string, int|bool|string|null> $balance @return array<string, int|bool|string|null> */
    private function paymentReadiness(array $balance, bool $includeAmounts): array
    {
        $base = [
            'reservation_payment_satisfied' => $balance['reservation_payment_satisfied'],
            'reservation_payment_overdue' => $balance['reservation_payment_overdue'],
            'unresolved_financial_obligations' => (int) $balance['remaining_minor'] > 0 || (int) $balance['verified_minor'] > 0,
        ];

        return $includeAmounts ? [...$base, ...$balance] : $base;
    }
}
