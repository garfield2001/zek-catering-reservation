<?php

namespace App\Actions\Cancellations;

use App\Enums\CancellationRequestStatus;
use App\Enums\ReservationStatus;
use App\Models\BusinessPolicy;
use App\Models\CancellationRequest;
use App\Models\CancellationRequestEvent;
use App\Models\Quotation;
use App\Models\Reservation;
use App\Models\ReservationChangeRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateCancellationRequest
{
    public function __construct(private AuditLogger $audit) {}

    /** @param array{idempotency_key: string, reason: string, internal_note?: string|null} $data */
    public function handle(?User $actor, Quotation $attempt, array $data, bool $fromCustomer, bool $fromBusiness = false): CancellationRequest
    {
        return DB::transaction(function () use ($actor, $attempt, $data, $fromCustomer, $fromBusiness): CancellationRequest {
            $quotation = Quotation::query()
                ->with(['reservationRequest', 'reservation', 'payments'])
                ->lockForUpdate()
                ->findOrFail($attempt->id);
            $existing = CancellationRequest::query()->where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first();
            if ($existing !== null) {
                if ($existing->quotation_id !== $quotation->id) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This request key was already used for a different cancellation case.']);
                }

                return $existing;
            }
            $reservation = $quotation->reservation;
            if ($reservation?->status === ReservationStatus::Completed) {
                throw ValidationException::withMessages(['reservation' => 'A completed reservation cannot be cancelled through the ordinary cancellation workflow.']);
            }
            if ($reservation?->status === ReservationStatus::Cancelled || $quotation->status->value === 'withdrawn') {
                throw ValidationException::withMessages(['cancellation' => 'This case has already been cancelled or withdrawn.']);
            }
            if ($reservation !== null && ReservationChangeRequest::query()
                ->where('reservation_id', $reservation->id)
                ->whereIn('status', ['pending_review', 'awaiting_customer_agreement', 'awaiting_reconfirmation'])
                ->lockForUpdate()
                ->exists()) {
                throw ValidationException::withMessages(['cancellation' => 'Resolve the pending reservation change request before starting cancellation. Its history will remain available.']);
            }
            if (CancellationRequest::query()
                ->where('quotation_id', $quotation->id)
                ->whereIn('status', ['pending_review', 'refund_under_review', 'refund_approved'])
                ->lockForUpdate()
                ->exists()) {
                throw ValidationException::withMessages(['cancellation' => 'A cancellation or refund review is already in progress for this case.']);
            }
            if ($fromBusiness && ($actor === null || ! $actor->active || ! $actor->isAdmin())) {
                throw ValidationException::withMessages(['cancellation' => 'Only an Administrator can initiate a cancellation on behalf of ZEK Catering.']);
            }

            $policy = BusinessPolicy::query()->where('key', 'current')->lockForUpdate()->sole();
            $kind = $reservation === null ? 'withdrawal' : 'cancellation';
            $financial = $this->financialSnapshot($quotation);
            $cancellation = CancellationRequest::create([
                'reservation_request_id' => $quotation->reservation_request_id,
                'quotation_id' => $quotation->id,
                'reservation_id' => $reservation?->id,
                'idempotency_key' => $data['idempotency_key'],
                'kind' => $kind,
                'status' => CancellationRequestStatus::PendingReview,
                'reason' => trim($data['reason']),
                'internal_note' => filled($data['internal_note'] ?? null) ? trim((string) $data['internal_note']) : null,
                'policy_snapshot' => $this->policySnapshot($policy, $quotation, $reservation),
                'reservation_snapshot' => $reservation === null ? null : $this->reservationSnapshot($reservation),
                'financial_snapshot' => $financial,
                'requested_by_customer' => $fromCustomer,
                'requested_by_business' => $fromBusiness,
                'requested_by' => $fromCustomer ? null : $actor?->id,
                'requested_at' => now(),
            ]);
            CancellationRequestEvent::create([
                'cancellation_request_id' => $cancellation->id,
                'from_status' => null,
                'to_status' => CancellationRequestStatus::PendingReview,
                'note' => $fromBusiness ? 'Cancellation initiated by ZEK Catering.' : null,
                'actor_id' => $fromCustomer ? null : $actor?->id,
            ]);
            $this->audit->record('cancellation.requested', $actor, $cancellation, null, [
                'quotation_id' => $quotation->id, 'reservation_id' => $reservation?->id,
                'kind' => $kind, 'requested_by_customer' => $fromCustomer, 'requested_by_business' => $fromBusiness,
            ]);

            return $cancellation;
        });
    }

    /** @return array<string, int|string> */
    private function financialSnapshot(Quotation $quotation): array
    {
        $verified = 0;
        $pending = 0;
        foreach ($quotation->payments as $payment) {
            if ($payment->status->value === 'verified') {
                $verified += (int) $payment->amount_minor;
            }
            if ($payment->status->value === 'pending_verification') {
                $pending += (int) $payment->amount_minor;
            }
        }

        return ['schema' => 1, 'verified_minor' => $verified, 'pending_minor' => $pending, 'captured_at' => now()->toIso8601String()];
    }

    /** @return array<string, mixed> */
    private function reservationSnapshot(Reservation $reservation): array
    {
        return [
            'reference' => $reservation->reference, 'status' => $reservation->status->value,
            'event_date' => $reservation->event_date->toDateString(), 'event_start_time' => $reservation->event_start_time,
            'venue_information' => $reservation->venue_information, 'guest_count' => $reservation->guest_count,
            'confirmed_quotation_version_id' => $reservation->confirmed_quotation_version_id,
            'confirmation_snapshot' => $reservation->confirmation_snapshot, 'capacity_snapshot' => $reservation->capacity_snapshot,
        ];
    }

    /** @return array<string, mixed> */
    private function policySnapshot(BusinessPolicy $policy, Quotation $quotation, ?Reservation $reservation): array
    {
        $eventDate = $reservation !== null
            ? $reservation->event_date->toDateString()
            : $quotation->event_date->toDateString();
        $deadline = Carbon::parse($eventDate, 'Asia/Manila')
            ->startOfDay()
            ->subDays($policy->cancellation_notice_days);

        return [
            ...$policy->cancellationSnapshot(),
            'event_date' => $eventDate,
            'notice_deadline' => $deadline->toDateString(),
            'inside_notice_period' => now('Asia/Manila')->greaterThanOrEqualTo($deadline),
            'evaluated_at' => now()->toIso8601String(),
        ];
    }
}
