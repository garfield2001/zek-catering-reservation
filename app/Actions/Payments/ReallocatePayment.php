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

class ReallocatePayment
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(User $actor, Payment $payment, QuotationVersion $target, string $reason): PaymentAllocation
    {
        return DB::transaction(function () use ($actor, $payment, $target, $reason): PaymentAllocation {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $target = QuotationVersion::query()->with('acceptance')->lockForUpdate()->findOrFail($target->id);
            if ($payment->status !== PaymentStatus::Verified || $target->acceptance === null) {
                throw ValidationException::withMessages(['payment' => 'Only a verified payment can be allocated to an accepted Version of its own quotation.']);
            }
            if ($payment->quotation_id !== null && $payment->quotation_id !== $target->quotation_id) {
                throw ValidationException::withMessages(['payment' => 'A payment can only be allocated to an accepted Version of its own quotation.']);
            }
            if ($payment->quotation_id === null && $payment->source !== 'administrator_exception') {
                throw ValidationException::withMessages(['payment' => 'Only an Administrator-recorded early exception can be allocated after it is linked to an accepted quotation.']);
            }
            $current = PaymentAllocation::query()->where('payment_id', $payment->id)->whereNull('superseded_at')->lockForUpdate()->first();
            if ($current?->quotation_version_id === $target->id) {
                throw ValidationException::withMessages(['quotation_version_id' => 'This payment is already allocated to that quotation Version.']);
            }
            if ($current !== null) {
                $current->forceFill(['superseded_at' => now(), 'superseded_by' => $actor->id, 'supersession_reason' => trim($reason)])->save();
            }
            if ($payment->quotation_id === null) {
                $payment->forceFill(['quotation_id' => $target->quotation_id, 'reservation_request_id' => $target->quotation?->reservation_request_id])->save();
            }
            $next = PaymentAllocation::create(['payment_id' => $payment->id, 'quotation_version_id' => $target->id, 'amount_minor' => $payment->amount_minor, 'allocated_by' => $actor->id, 'reason' => trim($reason)]);
            $this->audit->record('payment.reallocated', $actor, $payment, ['quotation_version_id' => $current?->quotation_version_id], ['quotation_version_id' => $target->id, 'reason' => trim($reason)]);

            return $next;
        });
    }
}
