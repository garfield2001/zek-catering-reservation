<?php

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\QuotationVersion;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewPayment
{
    public function __construct(private AuditLogger $audit) {}

    public function verify(User $actor, Payment $payment, ?QuotationVersion $version = null, ?int $verifiedAmountMinor = null, ?string $customerMessage = null): Payment
    {
        return DB::transaction(function () use ($actor, $payment, $version, $verifiedAmountMinor, $customerMessage): Payment {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($payment->status !== PaymentStatus::PendingVerification) {
                throw ValidationException::withMessages(['payment' => 'Only a pending payment can be verified.']);
            }
            $amount = $verifiedAmountMinor ?? $payment->amount_minor;
            if ($amount === null || $amount < 1) {
                throw ValidationException::withMessages(['verified_amount' => 'Enter the amount actually received before verifying this receipt.']);
            }
            $version ??= $payment->submittedQuotationVersion ?? $payment->quotation?->versions()->whereHas('acceptance')->orderByDesc('version_number')->first();
            if ($version === null && $payment->quotation_id === null && $payment->source === 'administrator_exception') {
                $payment->forceFill(['status' => PaymentStatus::Verified, 'amount_minor' => $amount, 'paid_at' => $payment->paid_at ?? now(), 'verified_by' => $actor->id, 'verified_at' => now(), 'customer_message' => $customerMessage])->save();
                $this->audit->record('payment.verified_early_exception', $actor, $payment, ['status' => 'pending_verification'], ['amount_minor' => (string) $amount]);

                return $payment;
            }
            if ($version === null) {
                throw ValidationException::withMessages(['quotation_version_id' => 'Select an accepted quotation version before verifying this payment.']);
            }
            if ($version->quotation_id !== $payment->quotation_id) {
                throw ValidationException::withMessages(['quotation_version_id' => 'A payment can only be allocated to its own quotation case.']);
            }
            $payment->forceFill(['status' => PaymentStatus::Verified, 'amount_minor' => $amount, 'paid_at' => $payment->paid_at ?? now(), 'verified_by' => $actor->id, 'verified_at' => now(), 'customer_message' => $customerMessage])->save();
            PaymentAllocation::create(['payment_id' => $payment->id, 'quotation_version_id' => $version->id, 'amount_minor' => $amount, 'allocated_by' => $actor->id, 'reason' => 'Verified payment allocation']);
            $this->audit->record('payment.verified', $actor, $payment, ['status' => 'pending_verification'], ['quotation_version_id' => $version->id, 'amount_minor' => (string) $amount]);

            return $payment;
        });
    }

    public function reject(User $actor, Payment $payment, string $reason): Payment
    {
        return DB::transaction(function () use ($actor, $payment, $reason): Payment {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($payment->status !== PaymentStatus::PendingVerification) {
                throw ValidationException::withMessages(['payment' => 'Only a pending payment can be rejected.']);
            }
            $payment->forceFill(['status' => PaymentStatus::Rejected, 'rejection_reason' => trim($reason), 'rejected_by' => $actor->id, 'rejected_at' => now()])->save();
            $this->audit->record('payment.rejected', $actor, $payment, ['status' => 'pending_verification'], ['reason' => trim($reason)]);

            return $payment;
        });
    }

    public function reverse(User $actor, Payment $payment, string $reason): Payment
    {
        return DB::transaction(function () use ($actor, $payment, $reason): Payment {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($payment->status !== PaymentStatus::Verified) {
                throw ValidationException::withMessages(['payment' => 'Only a verified payment can be reversed.']);
            }
            $payment->forceFill(['status' => PaymentStatus::Reversed, 'reversed_by' => $actor->id, 'reversed_at' => now(), 'reversal_reason' => trim($reason)])->save();
            $this->audit->record('payment.reversed', $actor, $payment, ['status' => 'verified'], ['reason' => trim($reason)]);

            return $payment;
        });
    }
}
