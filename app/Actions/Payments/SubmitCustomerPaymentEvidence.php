<?php

namespace App\Actions\Payments;

use App\Actions\Quotation\RecordQuotationAcceptance;
use App\Enums\QuotationStatus;
use App\Enums\QuotationVersionStatus;
use App\Models\BusinessPolicy;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\QuotationVersion;
use App\Services\Audit\AuditLogger;
use App\Services\Payments\CustomerPaymentUploadWindow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitCustomerPaymentEvidence
{
    public function __construct(
        private RecordPayment $payments,
        private RecordQuotationAcceptance $acceptance,
        private CustomerPaymentUploadWindow $window,
        private AuditLogger $audit,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(Quotation $quotation, array $data, UploadedFile $proof): Payment
    {
        $payment = DB::transaction(function () use ($quotation, $data, $proof): ?Payment {
            $quotation = Quotation::query()->with('reservationRequest')->lockForUpdate()->findOrFail($quotation->id);
            $version = QuotationVersion::query()->with('acceptance')->lockForUpdate()->findOrFail($quotation->current_version_id);
            $policy = BusinessPolicy::query()->where('key', 'current')->lockForUpdate()->sole();

            if ($version->status === QuotationVersionStatus::Issued) {
                if ($version->valid_until !== null && $version->valid_until->isPast()) {
                    $version->update(['status' => QuotationVersionStatus::Expired]);
                    $quotation->update(['status' => QuotationStatus::Expired]);
                    $this->audit->record('quotation.expired', null, $version, ['status' => 'issued'], ['valid_until' => $version->valid_until->toIso8601String()]);

                    return null;
                }
                $this->acceptance->online($version, $quotation->customer_name, 'customer-payment:'.$quotation->id);
                $version->refresh();
            }
            if ($version->status !== QuotationVersionStatus::Accepted || $version->acceptance === null) {
                throw ValidationException::withMessages(['payment' => 'A current accepted quotation is required before uploading payment evidence.']);
            }
            $this->window->assertOpen($quotation, $policy, $this->cutoffDays($version));

            $payment = $this->payments->handle(null, $quotation, [
                'idempotency_key' => $data['idempotency_key'],
                'submitted_quotation_version_id' => $version->id,
            ], 'customer_submission', $proof);
            $this->audit->record('payment.customer_evidence_submitted', null, $payment, null, [
                'quotation_id' => $quotation->id, 'quotation_version_id' => $version->id,
            ]);

            return $payment;
        });
        if ($payment === null) {
            throw ValidationException::withMessages(['payment' => 'This quotation has expired. Please start a new inquiry so ZEK Catering can review current availability.']);
        }

        return $payment;
    }

    private function cutoffDays(QuotationVersion $version): ?int
    {
        $value = $version->terms_snapshot['customer_payment_upload_cutoff_days'] ?? null;

        return is_int($value) || is_numeric($value) ? (int) $value : null;
    }
}
