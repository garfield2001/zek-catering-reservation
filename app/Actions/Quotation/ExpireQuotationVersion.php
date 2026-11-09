<?php

namespace App\Actions\Quotation;

use App\Enums\QuotationStatus;
use App\Enums\QuotationVersionStatus;
use App\Models\QuotationVersion;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class ExpireQuotationVersion
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(QuotationVersion $version): bool
    {
        return DB::transaction(function () use ($version): bool {
            $version = QuotationVersion::query()->with('quotation')->lockForUpdate()->findOrFail($version->id);
            if ($version->status !== QuotationVersionStatus::Issued || $version->valid_until?->isFuture()) {
                return false;
            }
            $version->update(['status' => QuotationVersionStatus::Expired]);
            if ($version->quotation->current_version_id === $version->id) {
                $version->quotation->update(['status' => QuotationStatus::Expired]);
            }
            $this->audit->record('quotation.expired', null, $version, ['status' => 'issued'], ['valid_until' => $version->valid_until?->toIso8601String()]);

            return true;
        });
    }
}
