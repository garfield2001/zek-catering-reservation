<?php

namespace App\Services\Payments;

use App\Models\BusinessPolicy;
use App\Models\Quotation;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class CustomerPaymentUploadWindow
{
    public function assertOpen(Quotation $quotation, BusinessPolicy $policy, ?int $snapshotCutoffDays = null): void
    {
        if ($quotation->event_date === null) {
            throw ValidationException::withMessages(['payment' => 'Payment uploads are unavailable until the event date is recorded. Contact ZEK Catering for assistance.']);
        }
        $cutoff = CarbonImmutable::parse($quotation->event_date->toDateString(), 'Asia/Manila')
            ->subDays($snapshotCutoffDays ?? $policy->customer_payment_upload_cutoff_days)
            ->endOfDay();
        if (CarbonImmutable::now('Asia/Manila')->greaterThan($cutoff)) {
            throw ValidationException::withMessages(['payment' => "The payment-upload deadline passed on {$cutoff->format('F j, Y')}. Contact ZEK Catering for assistance."]);
        }
    }

    public function cutoffAt(Quotation $quotation, BusinessPolicy $policy, ?int $snapshotCutoffDays = null): ?CarbonImmutable
    {
        if ($quotation->event_date === null) {
            return null;
        }

        return CarbonImmutable::parse($quotation->event_date->toDateString(), 'Asia/Manila')
            ->subDays($snapshotCutoffDays ?? $policy->customer_payment_upload_cutoff_days)
            ->endOfDay();
    }
}
