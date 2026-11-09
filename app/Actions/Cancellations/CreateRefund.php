<?php

namespace App\Actions\Cancellations;

use App\Enums\CancellationRequestStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\CancellationRequest;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\RefundEvent;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Cancellations\RefundBalance;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateRefund
{
    public function __construct(private RefundBalance $balances, private AuditLogger $audit) {}

    /** @param array{payment_id: int, amount_minor: int, reason: string, internal_note?: string|null, idempotency_key: string} $data */
    public function handle(User $actor, CancellationRequest $attempt, array $data): Refund
    {
        if (! $actor->active || ! $actor->isAdmin()) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($actor, $attempt, $data): Refund {
            $cancellation = CancellationRequest::query()->with('quotation')->lockForUpdate()->findOrFail($attempt->id);
            if (! in_array($cancellation->status, [
                CancellationRequestStatus::RefundUnderReview,
                CancellationRequestStatus::RefundApproved,
                CancellationRequestStatus::RefundCompleted,
            ], true)) {
                throw ValidationException::withMessages(['refund' => 'Finalize cancellation and complete financial review before recording a refund.']);
            }
            $existing = Refund::query()->where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first();
            if ($existing !== null) {
                if ($existing->cancellation_request_id !== $cancellation->id) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This request key was already used for another refund.']);
                }

                return $existing;
            }
            $payment = Payment::query()->lockForUpdate()->findOrFail($data['payment_id']);
            if ($payment->quotation_id !== $cancellation->quotation_id || $payment->status !== PaymentStatus::Verified) {
                throw ValidationException::withMessages(['payment_id' => 'Choose a verified payment from this cancellation case.']);
            }
            $amount = (int) $data['amount_minor'];
            $balance = $this->balances->forPayment($payment);
            $available = $balance['held_minor'] - $this->balances->reservedForRefund($payment);
            if ($amount <= 0 || $amount > $available) {
                throw ValidationException::withMessages(['amount_minor' => "The refund cannot exceed the PHP centavos available from this payment after other pending refund decisions ({$available})."]);
            }
            $refund = Refund::create([
                'cancellation_request_id' => $cancellation->id, 'payment_id' => $payment->id,
                'idempotency_key' => $data['idempotency_key'], 'amount_minor' => $amount,
                'status' => RefundStatus::PendingReview, 'reason' => trim($data['reason']),
                'internal_note' => filled($data['internal_note'] ?? null) ? trim((string) $data['internal_note']) : null,
                'requested_by' => $actor->id, 'requested_at' => now(),
            ]);
            RefundEvent::create(['refund_id' => $refund->id, 'from_status' => null, 'to_status' => RefundStatus::PendingReview, 'actor_id' => $actor->id]);
            if ($cancellation->status !== CancellationRequestStatus::RefundUnderReview) {
                $this->setCancellationStatus($cancellation, CancellationRequestStatus::RefundUnderReview, $actor, 'A refund requires Administrator review.');
            }
            $this->audit->record('refund.requested', $actor, $refund, null, ['cancellation_request_id' => $cancellation->id, 'payment_id' => $payment->id, 'amount_minor' => $amount]);

            return $refund;
        });
    }

    private function setCancellationStatus(CancellationRequest $cancellation, CancellationRequestStatus $next, User $actor, string $note): void
    {
        $before = $cancellation->status;
        $cancellation->forceFill(['status' => $next])->save();
        $cancellation->events()->create([
            'from_status' => $before, 'to_status' => $next, 'note' => $note, 'actor_id' => $actor->id,
        ]);
    }
}
