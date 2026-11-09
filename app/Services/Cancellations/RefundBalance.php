<?php

namespace App\Services\Cancellations;

use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\CancellationRequest;
use App\Models\Payment;
use App\Models\Refund;

class RefundBalance
{
    /** @return array{received_minor: int, refunded_minor: int, held_minor: int} */
    public function forPayment(Payment $payment): array
    {
        $completed = (int) Refund::query()
            ->where('payment_id', $payment->id)
            ->where('status', RefundStatus::Completed)
            ->sum('amount_minor');
        $corrections = (int) Refund::query()
            ->where('payment_id', $payment->id)
            ->where('status', RefundStatus::Completed)
            ->join('refund_corrections', 'refund_corrections.refund_id', '=', 'refunds.id')
            ->sum('refund_corrections.amount_minor');
        $received = $payment->status === PaymentStatus::Verified ? (int) $payment->amount_minor : 0;

        return [
            'received_minor' => $received,
            'refunded_minor' => $completed + $corrections,
            'held_minor' => max(0, $received - $completed - $corrections),
        ];
    }

    /**
     * Amount committed to a proposed or approved refund. It has not yet left
     * the business, but it cannot be offered a second time.
     */
    public function reservedForRefund(Payment $payment): int
    {
        return (int) Refund::query()
            ->where('payment_id', $payment->id)
            ->whereIn('status', [RefundStatus::PendingReview, RefundStatus::Approved])
            ->sum('amount_minor');
    }

    /** @return array{verified_minor: int, pending_minor: int, refunded_minor: int, held_minor: int} */
    public function forCancellation(CancellationRequest $cancellation): array
    {
        $payments = $cancellation->quotation->payments;
        $verified = 0;
        $pending = 0;
        $refunded = 0;
        foreach ($payments as $payment) {
            if ($payment->status === PaymentStatus::PendingVerification) {
                $pending += (int) $payment->amount_minor;
            }
            $balance = $this->forPayment($payment);
            $verified += $balance['received_minor'];
            $refunded += $balance['refunded_minor'];
        }

        return [
            'verified_minor' => $verified,
            'pending_minor' => $pending,
            'refunded_minor' => $refunded,
            'held_minor' => max(0, $verified - $refunded),
        ];
    }
}
