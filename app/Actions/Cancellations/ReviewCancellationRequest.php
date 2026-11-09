<?php

namespace App\Actions\Cancellations;

use App\Enums\CancellationRequestStatus;
use App\Enums\QuotationStatus;
use App\Enums\ReservationRequestStatus;
use App\Enums\ReservationStatus;
use App\Models\CancellationRequest;
use App\Models\CancellationRequestEvent;
use App\Models\ReservationChangeRequest;
use App\Models\ReservationRequestStatusEvent;
use App\Models\ReservationStatusEvent;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Cancellations\RefundBalance;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewCancellationRequest
{
    public function __construct(private RefundBalance $balances, private AuditLogger $audit) {}

    public function handle(User $actor, CancellationRequest $attempt, bool $approved, string $reviewNote): CancellationRequest
    {
        if (! $actor->active || ! $actor->isAdmin()) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($actor, $attempt, $approved, $reviewNote): CancellationRequest {
            $cancellation = CancellationRequest::query()
                ->with(['quotation.payments', 'quotation.reservationRequest', 'reservation'])
                ->lockForUpdate()
                ->findOrFail($attempt->id);
            if ($cancellation->status !== CancellationRequestStatus::PendingReview) {
                throw ValidationException::withMessages(['cancellation' => 'Only a pending cancellation request can be reviewed.']);
            }
            if (! filled($reviewNote)) {
                throw ValidationException::withMessages(['review_note' => 'Record the Administrator review decision and reason.']);
            }
            $before = $cancellation->status->value;
            if (! $approved) {
                $cancellation->forceFill([
                    'status' => CancellationRequestStatus::Declined,
                    'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'review_note' => trim($reviewNote),
                ])->save();
                $this->event($cancellation, $before, CancellationRequestStatus::Declined, $reviewNote, $actor);
                $this->audit->record('cancellation.declined', $actor, $cancellation, ['status' => $before], ['status' => 'declined']);

                return $cancellation->fresh();
            }
            $reservation = $cancellation->reservation;
            if ($reservation !== null) {
                $reservation = $cancellation->reservation()->lockForUpdate()->firstOrFail();
                if ($reservation->status !== ReservationStatus::Confirmed) {
                    throw ValidationException::withMessages(['reservation' => 'Only an active confirmed reservation can be finalized as cancelled.']);
                }
                if (ReservationChangeRequest::query()->where('reservation_id', $reservation->id)
                    ->whereIn('status', ['pending_review', 'awaiting_customer_agreement', 'awaiting_reconfirmation'])->lockForUpdate()->exists()) {
                    throw ValidationException::withMessages(['cancellation' => 'Resolve the pending reservation change before finalizing cancellation.']);
                }
                $reservation->forceFill([
                    'status' => ReservationStatus::Cancelled,
                    'cancelled_by' => $actor->id, 'cancelled_at' => now(), 'cancellation_note' => trim($reviewNote),
                ])->save();
                ReservationStatusEvent::create([
                    'reservation_id' => $reservation->id, 'from_status' => ReservationStatus::Confirmed,
                    'to_status' => ReservationStatus::Cancelled, 'note' => trim($reviewNote), 'changed_by' => $actor->id,
                ]);
            } else {
                $cancellation->quotation->forceFill(['status' => QuotationStatus::Withdrawn])->save();
                $request = $cancellation->quotation->reservationRequest;
                if ($request !== null && $request->status !== ReservationRequestStatus::Closed) {
                    $from = $request->status;
                    $request->forceFill(['status' => ReservationRequestStatus::Closed])->save();
                    ReservationRequestStatusEvent::create([
                        'reservation_request_id' => $request->id, 'from_status' => $from,
                        'to_status' => ReservationRequestStatus::Closed, 'changed_by' => $actor->id,
                    ]);
                }
            }
            $financial = $this->balances->forCancellation($cancellation);
            $next = $financial['verified_minor'] > 0 || $financial['pending_minor'] > 0
                ? CancellationRequestStatus::RefundUnderReview
                : CancellationRequestStatus::Cancelled;
            $cancellation->forceFill([
                'status' => $next, 'reviewed_by' => $actor->id, 'reviewed_at' => now(),
                'review_note' => trim($reviewNote), 'finalized_by' => $actor->id, 'finalized_at' => now(),
            ])->save();
            $this->event($cancellation, $before, $next, $reviewNote, $actor);
            $this->audit->record('cancellation.finalized', $actor, $cancellation, ['status' => $before], [
                'status' => $next->value, 'reservation_id' => $reservation?->id, 'financial_snapshot' => $financial,
            ]);

            return $cancellation->fresh(['reservation', 'quotation']);
        });
    }

    private function event(CancellationRequest $cancellation, string $from, CancellationRequestStatus $to, string $note, User $actor): void
    {
        CancellationRequestEvent::create([
            'cancellation_request_id' => $cancellation->id, 'from_status' => $from,
            'to_status' => $to, 'note' => trim($note), 'actor_id' => $actor->id,
        ]);
    }
}
