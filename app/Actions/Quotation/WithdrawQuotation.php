<?php

namespace App\Actions\Quotation;

use App\Enums\QuotationStatus;
use App\Enums\QuotationVersionStatus;
use App\Models\QuotationVersion;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WithdrawQuotation
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(User $actor, QuotationVersion $version, string $reason): QuotationVersion
    {
        return DB::transaction(function () use ($actor, $version, $reason): QuotationVersion {
            $version = QuotationVersion::query()->with('quotation')->lockForUpdate()->findOrFail($version->id);
            if ($version->status !== QuotationVersionStatus::Issued) {
                throw ValidationException::withMessages(['quotation' => 'Only an issued quotation can be withdrawn.']);
            }
            $version->update(['status' => QuotationVersionStatus::Withdrawn, 'withdrawn_at' => now(), 'withdrawn_by' => $actor->id, 'withdrawal_reason' => trim($reason)]);
            if ($version->quotation->current_version_id === $version->id) {
                $version->quotation->update(['status' => QuotationStatus::Withdrawn]);
            }
            $this->audit->record('quotation.withdrawn', $actor, $version, ['status' => 'issued'], ['reason' => trim($reason)]);

            return $version->refresh();
        });
    }
}
