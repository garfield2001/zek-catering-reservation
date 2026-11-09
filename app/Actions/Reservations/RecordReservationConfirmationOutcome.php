<?php

namespace App\Actions\Reservations;

use App\Models\Quotation;
use App\Models\ReservationConfirmationEvent;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Payments\PaymentBalance;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordReservationConfirmationOutcome
{
    public function __construct(private PaymentBalance $balances, private AuditLogger $audit) {}

    public function handle(User $actor, Quotation $quotation, string $outcome, string $reason): ReservationConfirmationEvent
    {
        if (! $actor->active || ! $actor->isAdmin()) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($actor, $quotation, $outcome, $reason): ReservationConfirmationEvent {
            $quotation = Quotation::query()->with('reservation')->lockForUpdate()->findOrFail($quotation->id);
            if ($quotation->reservation !== null) {
                throw ValidationException::withMessages(['reservation' => 'A confirmed reservation cannot be deferred or declined. Cancellation and rescheduling remain separate workflows.']);
            }
            $version = $quotation->versions()->with('acceptance')->whereHas('acceptance')->reorder('version_number', 'desc')->lockForUpdate()->first();
            if ($version === null || $version->acceptance === null) {
                throw ValidationException::withMessages(['quotation' => 'An accepted quotation Version is required before recording a confirmation decision.']);
            }
            $payment = $this->balances->forVersion($version);
            $event = ReservationConfirmationEvent::create([
                'quotation_id' => $quotation->id,
                'quotation_version_id' => $version->id,
                'outcome' => $outcome,
                'reason' => trim($reason),
                'payment_snapshot' => [
                    'quotation_version_id' => $payment['quotation_version_id'],
                    'reservation_required_minor' => $payment['reservation_required_minor'],
                    'verified_minor' => $payment['verified_minor'],
                    'reservation_remaining_minor' => $payment['reservation_remaining_minor'],
                    'reservation_payment_satisfied' => $payment['reservation_payment_satisfied'],
                    'reservation_payment_overdue' => $payment['reservation_payment_overdue'],
                    'remaining_minor' => $payment['remaining_minor'],
                    'unresolved_financial_obligations' => ((int) $payment['remaining_minor']) > 0 || ((int) $payment['verified_minor']) > 0,
                ],
                'recorded_by' => $actor->id,
            ]);
            $this->audit->record("reservation.confirmation_{$outcome}", $actor, $quotation, null, [
                'quotation_version_id' => $version->id,
                'reason' => trim($reason),
                'unresolved_financial_obligations' => $event->payment_snapshot['unresolved_financial_obligations'],
            ]);

            return $event;
        });
    }
}
