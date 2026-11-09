<?php

namespace App\Actions\Quotation;

use App\Enums\QuotationVersionStatus;
use App\Models\QuotationAdjustmentRequest;
use App\Models\QuotationVersion;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Catalog\MinorMoney;
use Illuminate\Validation\ValidationException;

class RequestQuotationAdjustment
{
    public function __construct(private MinorMoney $money, private AuditLogger $audit) {}

    public function handle(User $actor, QuotationVersion $version, string $amount, string $direction, string $reason): QuotationAdjustmentRequest
    {
        if ($version->status !== QuotationVersionStatus::Draft) {
            throw ValidationException::withMessages(['adjustment' => 'Exceptional pricing can only be requested for a quotation draft.']);
        }
        $minor = $this->money->fromPesos($amount, 'adjustment_amount');
        if ($minor === 0) {
            throw ValidationException::withMessages(['adjustment_amount' => 'Enter a non-zero exceptional adjustment.']);
        }
        $signed = $direction === 'discount' ? -$minor : $minor;
        if ($signed < 0 && $this->money->add(0, (int) $version->subtotal_minor) < $minor) {
            throw ValidationException::withMessages(['adjustment_amount' => 'A discount cannot reduce the quotation below zero.']);
        }
        $request = QuotationAdjustmentRequest::create([
            'quotation_version_id' => $version->id, 'requested_adjustment_minor' => $signed,
            'reason' => trim($reason), 'status' => 'pending', 'requested_by' => $actor->id,
        ]);
        $this->audit->record('quotation.adjustment_requested', $actor, $version, null, ['adjustment_request_id' => $request->id, 'direction' => $direction, 'reason' => trim($reason)]);

        return $request;
    }
}
