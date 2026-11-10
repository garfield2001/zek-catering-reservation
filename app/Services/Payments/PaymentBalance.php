<?php

namespace App\Services\Payments;

use App\Models\Quotation;
use App\Models\QuotationVersion;

class PaymentBalance
{
    /** @return array<string, int|bool|string|null> */
    public function forQuotation(Quotation $quotation): array
    {
        $version = $quotation->versions()->whereHas('acceptance')->reorder('version_number', 'desc')->first();
        if ($version === null) {
            return $this->empty();
        }

        return $this->forVersion($version);
    }

    /** @return array<string, int|bool|string|null> */
    public function forVersion(QuotationVersion $version): array
    {
        $version->loadMissing('acceptance');
        $acceptance = $version->acceptance;
        if ($acceptance === null) {
            return $this->empty();
        }
        $total = (int) $version->total_minor;
        $terms = $acceptance->terms_snapshot ?? $version->terms_snapshot ?? [];
        $payment = $terms['reservation_payment'] ?? [];
        $required = ($payment['type'] ?? null) === 'percentage'
            ? (int) floor(($total * (int) ($payment['basis_points'] ?? 0) + 5000) / 10000)
            : (int) ($payment['amount_minor'] ?? 0);
        $required = min(max(0, $required), $total);
        $paid = (int) $version->paymentAllocations()->whereNull('superseded_at')->whereHas('payment', fn ($query) => $query->where('status', 'verified'))->sum('amount_minor');
        $remaining = max(0, $total - $paid);
        $reservationRemaining = max(0, $required - $paid);
        $now = now('Asia/Manila');

        return [
            'quotation_version_id' => $version->id, 'total_minor' => $total, 'verified_minor' => $paid,
            'remaining_minor' => $remaining, 'reservation_required_minor' => $required,
            'reservation_remaining_minor' => $reservationRemaining, 'overpayment_minor' => max(0, $paid - $total),
            'reservation_payment_satisfied' => $reservationRemaining === 0,
            'final_payment_complete' => $remaining === 0,
            'unresolved_financial_obligations' => $remaining > 0,
            'reservation_payment_due_at' => $acceptance->payment_due_at->toIso8601String(),
            'final_payment_due_at' => $acceptance->final_payment_due_at?->toIso8601String(),
            'reservation_payment_overdue' => $reservationRemaining > 0 && $acceptance->payment_due_at->isPast(),
            'final_payment_overdue' => $remaining > 0 && $acceptance->final_payment_due_at?->isPast(),
        ];
    }

    /** @return array<string, int|bool|string|null> */
    private function empty(): array
    {
        return ['quotation_version_id' => null, 'total_minor' => 0, 'verified_minor' => 0, 'remaining_minor' => 0, 'reservation_required_minor' => 0, 'reservation_remaining_minor' => 0, 'overpayment_minor' => 0, 'reservation_payment_satisfied' => false, 'final_payment_complete' => false, 'unresolved_financial_obligations' => false, 'reservation_payment_due_at' => null, 'final_payment_due_at' => null, 'reservation_payment_overdue' => false, 'final_payment_overdue' => false];
    }
}
