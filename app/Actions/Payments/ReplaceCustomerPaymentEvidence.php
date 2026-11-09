<?php

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentEvidenceVersion;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ReplaceCustomerPaymentEvidence
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(Payment $payment, UploadedFile $proof): Payment
    {
        $path = null;
        try {
            return DB::transaction(function () use ($payment, $proof, &$path): Payment {
                $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
                if (! in_array($payment->status, [PaymentStatus::PendingVerification, PaymentStatus::Rejected], true)) {
                    throw ValidationException::withMessages(['payment' => 'Only pending or rejected evidence can be replaced. Verified evidence is immutable.']);
                }
                $hash = hash_file('sha256', $proof->getRealPath());
                if (PaymentEvidenceVersion::query()->where('sha256', $hash)->exists()) {
                    throw ValidationException::withMessages(['proof' => 'This receipt image was already submitted.']);
                }
                $path = $proof->store('payments/'.now()->format('Y/m'), 'local');
                $next = ((int) PaymentEvidenceVersion::query()->where('payment_id', $payment->id)->max('version_number')) + 1;
                PaymentEvidenceVersion::create([
                    'payment_id' => $payment->id, 'version_number' => $next, 'disk' => 'local', 'path' => $path,
                    'mime' => $proof->getMimeType(), 'sha256' => $hash, 'submitted_at' => now(),
                ]);
                $before = ['status' => $payment->status->value, 'proof_sha256' => $payment->proof_sha256];
                $payment->forceFill([
                    'status' => PaymentStatus::PendingVerification,
                    'proof_disk' => 'local', 'proof_path' => $path, 'proof_mime' => $proof->getMimeType(), 'proof_sha256' => $hash,
                    'rejection_reason' => null, 'rejected_by' => null, 'rejected_at' => null,
                ])->save();
                $this->audit->record('payment.evidence_replaced', null, $payment, $before, ['evidence_version' => $next, 'status' => 'pending_verification']);

                return $payment->refresh();
            });
        } catch (\Throwable $exception) {
            if ($path !== null) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }
    }
}
