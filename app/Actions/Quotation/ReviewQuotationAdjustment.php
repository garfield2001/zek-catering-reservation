<?php

namespace App\Actions\Quotation;

use App\Models\QuotationAdjustmentRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Catalog\MinorMoney;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewQuotationAdjustment
{
    public function __construct(private MinorMoney $money, private AuditLogger $audit) {}

    public function handle(User $actor, QuotationAdjustmentRequest $request, bool $approved, ?string $note): QuotationAdjustmentRequest
    {
        return DB::transaction(function () use ($actor, $request, $approved, $note): QuotationAdjustmentRequest {
            $request = QuotationAdjustmentRequest::query()->with('quotationVersion')->lockForUpdate()->findOrFail($request->id);
            if ($request->status !== 'pending' || $request->quotationVersion->status->value !== 'draft') {
                throw ValidationException::withMessages(['adjustment' => 'Only a pending request for a current quotation draft can be reviewed.']);
            }
            $request->update(['status' => $approved ? 'approved' : 'declined', 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'review_note' => filled($note) ? trim($note) : null]);
            if ($approved) {
                $version = $request->quotationVersion;
                $subtotal = $this->money->parse($version->subtotal_minor);
                $adjustment = (int) $request->requested_adjustment_minor;
                if ($adjustment < 0 && -$adjustment > $subtotal) {
                    throw ValidationException::withMessages(['adjustment' => 'An approved discount cannot reduce the quotation below zero.']);
                }
                $version->update(['adjustment_minor' => $adjustment, 'total_minor' => $subtotal + $adjustment]);
            }
            $this->audit->record('quotation.adjustment_'.($approved ? 'approved' : 'declined'), $actor, $request->quotationVersion, ['adjustment_request_id' => $request->id, 'status' => 'pending'], ['adjustment_request_id' => $request->id, 'status' => $request->status]);

            return $request->refresh();
        });
    }
}
