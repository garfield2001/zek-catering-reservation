<?php

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentEvidenceVersion;
use App\Models\PaymentMethod;
use App\Models\Quotation;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class RecordPayment
{
    public function __construct(private AuditLogger $audit) {}

    /** @param array<string,mixed> $data */
    public function handle(?User $actor, ?Quotation $quotation, array $data, string $source, ?UploadedFile $proof = null, bool $verify = false): Payment
    {
        $path = null;
        try {
            return DB::transaction(function () use ($actor, $quotation, $data, $source, $proof, $verify, &$path): Payment {
                $method = isset($data['payment_method_id'])
                    ? PaymentMethod::query()->lockForUpdate()->findOrFail($data['payment_method_id'])
                    : null;
                if ($method !== null && ! $method->is_active) {
                    throw ValidationException::withMessages(['payment_method_id' => 'This payment method is no longer available.']);
                }
                if ($source !== 'customer_submission' && $method === null) {
                    throw ValidationException::withMessages(['payment_method_id' => 'Choose the payment method used for this recorded payment.']);
                }
                if ($source === 'customer_submission' && $proof === null) {
                    throw ValidationException::withMessages(['proof' => 'Attach the receipt or payment proof.']);
                }
                $payment = Payment::query()->where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first();
                if ($payment !== null) {
                    return $payment;
                }
                if ($proof !== null) {
                    $path = $proof->store('payments/'.now()->format('Y/m'), 'local');
                }
                $hash = $proof ? hash_file('sha256', $proof->getRealPath()) : null;
                $payment = Payment::create([
                    'quotation_id' => $quotation?->id, 'reservation_request_id' => $quotation?->reservation_request_id,
                    'submitted_quotation_version_id' => $data['submitted_quotation_version_id'] ?? null,
                    'payment_method_id' => $method?->id, 'status' => $verify ? PaymentStatus::Verified : PaymentStatus::PendingVerification,
                    'source' => $source, 'amount_minor' => $data['amount_minor'] ?? null, 'paid_at' => $data['paid_at'] ?? null,
                    'transaction_reference' => $data['transaction_reference'] ?? null, 'notes' => $data['notes'] ?? null,
                    'proof_disk' => $path ? 'local' : null, 'proof_path' => $path, 'proof_mime' => $proof?->getMimeType(), 'proof_sha256' => $hash,
                    'idempotency_key' => $data['idempotency_key'], 'recorded_by' => $actor?->id,
                    'verified_by' => $verify ? $actor?->id : null, 'verified_at' => $verify ? now() : null,
                    'exception_reason' => $data['exception_reason'] ?? null,
                ]);
                if ($path !== null && $hash !== null) {
                    PaymentEvidenceVersion::create([
                        'payment_id' => $payment->id, 'version_number' => 1, 'disk' => 'local', 'path' => $path,
                        'mime' => $proof->getMimeType(), 'sha256' => $hash, 'submitted_at' => now(),
                    ]);
                }
                $this->audit->record($verify ? 'payment.recorded_and_verified' : 'payment.submitted', $actor, $payment, null, ['amount_minor' => $payment->amount_minor === null ? null : (string) $payment->amount_minor, 'method_code' => $method?->code, 'source' => $source]);

                return $payment;
            });
        } catch (UniqueConstraintViolationException $exception) {
            if ($path !== null) {
                Storage::disk('local')->delete($path);
            }
            if (str_contains($exception->getMessage(), 'payments_idempotency_key_unique')) {
                $existing = Payment::query()->where('idempotency_key', $data['idempotency_key'])->first();
                if ($existing !== null) {
                    return $existing;
                }
            }
            if (str_contains($exception->getMessage(), 'payments_unique_proof_hash') || str_contains($exception->getMessage(), 'payment_evidence_versions_sha256_unique')) {
                throw ValidationException::withMessages(['proof' => 'This payment proof was already submitted and still requires review.']);
            }
            if (str_contains($exception->getMessage(), 'payments_unique_transaction_reference')) {
                throw ValidationException::withMessages(['transaction_reference' => 'This payment reference was already submitted and still requires review.']);
            }

            throw $exception;
        } catch (\Throwable $exception) {
            if ($path !== null) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }
    }
}
