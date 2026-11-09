<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\QuotationVersionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReservationRequest\AccessCustomerProgressRequest;
use App\Models\BusinessPolicy;
use App\Models\CancellationRequest;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\QuotationVersion;
use App\Models\Refund;
use App\Models\Reservation;
use App\Models\ReservationRequest;
use App\Queries\Reservations\ReservationChangeOfferings;
use App\Services\BusinessPolicies\CurrentBusinessPolicy;
use App\Services\Payments\CustomerPaymentUploadWindow;
use App\Services\Payments\PaymentBalance;
use App\Services\ReservationRequest\CustomerProgressAccess;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerProgressController extends Controller
{
    public function lookup(): Response
    {
        return Inertia::render('storefront/customer-progress/lookup');
    }

    public function access(AccessCustomerProgressRequest $request, CustomerProgressAccess $access): RedirectResponse
    {
        $reservationRequest = $access->grant(
            $request,
            $request->validated('reference'),
            $request->validated('mobile_number'),
            $request->validated('access_code'),
        );

        return redirect()->route('storefront.customer-progress.show', $reservationRequest->reference);
    }

    public function show(Request $http, string $reference, CustomerProgressAccess $access, PaymentBalance $balances, CurrentBusinessPolicy $policies, CustomerPaymentUploadWindow $window, ReservationChangeOfferings $changeOfferings): Response
    {
        $request = ReservationRequest::query()->where('reference', $reference)->firstOrFail();
        $access->require($http, $request);
        $request->load(['lines', 'quotation.currentVersion.acceptance', 'quotation.versions.acceptance', 'quotation.payments.method', 'quotation.payments.allocation', 'quotation.reservation.changeRequests.quotationVersion.acceptance', 'quotation.reservation.changeRequests.events', 'quotation.cancellationRequests.refunds']);
        $policy = $policies->current();

        return Inertia::render('storefront/customer-progress/show', [
            'request' => $this->requestData($request, $balances, $policy, $window),
            'changeMenuOffering' => $this->changeMenuOffering($request, $changeOfferings),
            'changeAddonOfferings' => $changeOfferings->addons(),
        ]);
    }

    /** @return array<string, mixed> */
    private function requestData(ReservationRequest $request, PaymentBalance $balances, BusinessPolicy $policy, CustomerPaymentUploadWindow $window): array
    {
        $reservation = $request->quotation === null
            ? null
            : Reservation::query()->where('quotation_id', $request->quotation->id)->first();
        $current = $request->quotation?->currentVersion;
        // A newly issued revision is the commercial version the customer is
        // being shown and, therefore, the only Version a first receipt may
        // accept. A later Draft remains internal until it is issued, so the
        // customer continues to see the last accepted Version in that case.
        $version = $current !== null && $current->status !== QuotationVersionStatus::Draft
            ? $current
            : $request->quotation?->versions->filter(fn (QuotationVersion $item) => $item->acceptance !== null)->sortByDesc('version_number')->first();
        $acceptance = $version?->acceptance;
        $cutoffDays = $version?->terms_snapshot['customer_payment_upload_cutoff_days'] ?? null;
        $cutoff = $request->quotation ? $window->cutoffAt($request->quotation, $policy, is_numeric($cutoffDays) ? (int) $cutoffDays : null) : null;
        $uploadOpen = $cutoff === null || ! now('Asia/Manila')->greaterThan($cutoff);
        $eventStartTime = $reservation === null ? $request->event_start_time : $reservation->event_start_time;
        $venueInformation = $reservation === null ? $request->venue_information : $reservation->venue_information;
        $guestCount = $reservation === null ? $request->guest_count : $reservation->guest_count;

        return [
            'reference' => $request->reference,
            'status' => $request->status->value,
            'customer_name' => $request->customer_name,
            'event_date' => $reservation?->event_date->toDateString() ?? $request->event_date->toDateString(),
            'event_start_time' => $eventStartTime,
            'event_end_time' => $reservation === null || $reservation->event_start_time === null
                ? $request->event_end_time
                : CarbonImmutable::createFromFormat('Y-m-d H:i:s', $reservation->event_date->toDateString().' '.$reservation->event_start_time, 'Asia/Manila')->addHours($policy->event_duration_hours)->format('H:i:s'),
            'venue_information' => $venueInformation,
            'guest_count' => $guestCount,
            'estimated_subtotal_minor' => $request->estimated_subtotal_minor,
            'reservation' => $reservation ? [
                'status' => $reservation->status->value,
                'can_request_changes' => $reservation->status->value === 'confirmed',
                'change_requests' => $reservation->changeRequests->map(fn ($change) => [
                    'id' => $change->id, 'status' => $change->status->value, 'change_types' => $change->change_types,
                    'requested_at' => $change->requested_at->toIso8601String(), 'review_note' => $change->review_note,
                    'requires_quotation_revision' => $change->requires_quotation_revision,
                    'quotation_version' => $change->quotationVersion ? [
                        'id' => $change->quotationVersion->id,
                        'version_number' => $change->quotationVersion->version_number,
                        'status' => $change->quotationVersion->status->value,
                        'total_minor' => $change->quotationVersion->total_minor,
                        'accepted' => $change->quotationVersion->acceptance !== null,
                    ] : null,
                ])->values(),
            ] : null,
            'cancellation' => $request->quotation ? [
                'can_request' => $this->canRequestCancellation($request->quotation, $reservation),
                'guidance' => $policy->cancellation_refund_guidance,
                'notice_days' => $policy->cancellation_notice_days,
                'requests' => $request->quotation->cancellationRequests->map(fn (CancellationRequest $cancellation) => [
                    'id' => $cancellation->id, 'kind' => $cancellation->kind,
                    'status' => $cancellation->status->value,
                    'requested_at' => $cancellation->requested_at->toIso8601String(),
                    'refunds' => $cancellation->refunds->where('status', 'completed')->map(fn (Refund $refund) => [
                        'id' => $refund->id, 'amount_minor' => $refund->amount_minor,
                        'completed_at' => $refund->completed_at?->toIso8601String(),
                    ])->values(),
                ])->values(),
            ] : null,
            'quotation' => $version ? [
                'id' => $version->id,
                'reference' => $request->quotation->reference,
                'status' => $version->status->value,
                'valid_until' => $version->valid_until?->toIso8601String(),
                'line_snapshots' => $version->line_snapshots,
                'total_minor' => $version->total_minor,
                'terms_snapshot' => $version->terms_snapshot,
                'payment_instructions' => [
                    'account_name' => $policy->payment_account_name,
                    'ewallet_number' => $policy->payment_ewallet_number,
                    'bank_account' => $policy->payment_bank_account,
                    'additional_instructions' => $policy->payment_additional_instructions,
                    'contains_placeholders' => str_contains($policy->payment_account_name, 'SAMPLE')
                        || str_contains($policy->payment_ewallet_number, 'REPLACE WITH')
                        || str_contains($policy->payment_bank_account, 'REPLACE WITH'),
                ],
                'payment_upload_cutoff_at' => $cutoff?->toIso8601String(),
                'payment_upload_open' => $uploadOpen,
                'acceptance' => $acceptance ? [
                    'accepted_at' => $acceptance->accepted_at->toIso8601String(),
                    'payment_due_at' => $acceptance->payment_due_at->toIso8601String(),
                    'final_payment_due_at' => $acceptance->final_payment_due_at?->toIso8601String(),
                    'menu_finalization_due_at' => $acceptance->menu_finalization_due_at->toIso8601String(),
                ] : null,
                'payment_summary' => $acceptance ? $balances->forQuotation($request->quotation) : null,
                'payments' => $request->quotation->payments->map(fn (Payment $payment) => [
                    'id' => $payment->id, 'status' => $payment->status->value, 'amount_minor' => $payment->amount_minor,
                    'paid_at' => $payment->paid_at?->toIso8601String(), 'method' => $payment->method?->name,
                    'transaction_reference' => $payment->transaction_reference, 'rejection_reason' => $payment->rejection_reason,
                    'customer_message' => $payment->customer_message,
                ])->values(),
            ] : null,
        ];
    }

    /** @return array<string, mixed>|null */
    private function changeMenuOffering(ReservationRequest $request, ReservationChangeOfferings $offerings): ?array
    {
        $reservation = $request->quotation === null
            ? null
            : Reservation::query()->where('quotation_id', $request->quotation->id)->first();

        return $reservation === null ? null : $offerings->mainFor($reservation);
    }

    private function canRequestCancellation(Quotation $quotation, ?Reservation $reservation): bool
    {
        if ($quotation->status->value === 'withdrawn'
            || ($reservation !== null && $reservation->status->value !== 'confirmed')) {
            return false;
        }

        return ! $quotation->cancellationRequests
            ->contains(fn ($cancellation) => in_array($cancellation->status->value, [
                'pending_review', 'refund_under_review', 'refund_approved',
            ], true));
    }
}
