<?php

namespace App\Actions\Quotation;

use App\Enums\QuotationVersionStatus;
use App\Models\QuotationAdjustmentRequest;
use App\Models\QuotationVersion;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Catalog\MinorMoney;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApplyDirectQuotationAdjustment
{
    public function __construct(private MinorMoney $money, private AuditLogger $audit) {}

    public function handle(User $actor, QuotationVersion $version, string $amount, string $direction, string $reason): QuotationAdjustmentRequest
    {
        return DB::transaction(function () use ($actor, $version, $amount, $direction, $reason): QuotationAdjustmentRequest {
            $version = QuotationVersion::query()->lockForUpdate()->findOrFail($version->id);
            if ($version->status !== QuotationVersionStatus::Draft) {
                throw ValidationException::withMessages(['adjustment' => 'An Administrator can adjust only a current quotation draft. Create a revision for an issued quotation.']);
            }
            if (QuotationAdjustmentRequest::query()->where('quotation_version_id', $version->id)->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['adjustment' => 'Review the pending Staff adjustment request before applying a separate Administrator adjustment.']);
            }
            $minor = $this->money->fromPesos($amount, 'adjustment_amount');
            if ($minor === 0) {
                throw ValidationException::withMessages(['adjustment_amount' => 'Enter a non-zero exceptional adjustment.']);
            }
            $signed = $direction === 'discount' ? -$minor : $minor;
            $subtotal = $this->money->parse($version->subtotal_minor);
            if ($signed < 0 && -$signed > $subtotal) {
                throw ValidationException::withMessages(['adjustment_amount' => 'A discount cannot reduce the quotation below zero.']);
            }
            $originalAdjustment = (int) $version->adjustment_minor;
            $originalTotal = $this->money->parse($version->total_minor);
            $adjustment = QuotationAdjustmentRequest::create([
                'quotation_version_id' => $version->id, 'requested_adjustment_minor' => $signed,
                'reason' => trim($reason), 'status' => 'approved', 'requested_by' => $actor->id,
                'reviewed_by' => $actor->id, 'reviewed_at' => now(),
                'review_note' => 'Applied directly by an Administrator.',
            ]);
            $version->update(['adjustment_minor' => $signed, 'total_minor' => $subtotal + $signed]);
            $this->audit->record('quotation.adjustment_applied_directly', $actor, $version, [
                'adjustment_minor' => $originalAdjustment, 'total_minor' => $originalTotal,
            ], [
                'adjustment_request_id' => $adjustment->id, 'adjustment_minor' => $signed,
                'total_minor' => $subtotal + $signed, 'direction' => $direction, 'reason' => trim($reason),
            ]);

            return $adjustment;
        });
    }
}
