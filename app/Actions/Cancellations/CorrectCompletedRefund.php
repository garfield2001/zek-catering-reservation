<?php

namespace App\Actions\Cancellations;

use App\Enums\RefundStatus;
use App\Models\Refund;
use App\Models\RefundCorrection;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Cancellations\RefundBalance;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CorrectCompletedRefund
{
    public function __construct(private RefundBalance $balances, private AuditLogger $audit) {}

    public function handle(User $actor, Refund $attempt, int $amountMinor, string $reason): RefundCorrection
    {
        if (! $actor->active || ! $actor->isAdmin()) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($actor, $attempt, $amountMinor, $reason): RefundCorrection {
            $refund = Refund::query()->with('payment')->lockForUpdate()->findOrFail($attempt->id);
            if ($refund->status !== RefundStatus::Completed) {
                throw ValidationException::withMessages(['refund' => 'Only a completed refund can receive an auditable correction.']);
            }
            if ($amountMinor === 0 || ! filled($reason)) {
                throw ValidationException::withMessages(['amount_minor' => 'Enter a non-zero correction and explain it.']);
            }
            $payment = $refund->payment()->lockForUpdate()->firstOrFail();
            $existingForRefund = (int) $refund->corrections()->sum('amount_minor');
            if ((int) $refund->amount_minor + $existingForRefund + $amountMinor < 0) {
                throw ValidationException::withMessages(['amount_minor' => 'A correction cannot reduce this completed refund below zero.']);
            }
            if ($amountMinor > 0 && $amountMinor > $this->balances->forPayment($payment)['held_minor']) {
                throw ValidationException::withMessages(['amount_minor' => 'This correction exceeds the verified money still held from the linked payment.']);
            }
            $correction = RefundCorrection::create([
                'refund_id' => $refund->id, 'amount_minor' => $amountMinor,
                'reason' => trim($reason), 'recorded_by' => $actor->id,
            ]);
            $this->audit->record('refund.corrected', $actor, $refund, null, [
                'refund_correction_id' => $correction->id, 'amount_minor' => (string) $amountMinor, 'reason' => trim($reason),
            ]);

            return $correction;
        });
    }
}
