<?php

namespace App\Actions\Quotation;

use App\Enums\QuotationStatus;
use App\Enums\QuotationVersionStatus;
use App\Models\Quotation;
use App\Models\QuotationVersion;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateQuotationRevision
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(User $actor, Quotation $quotation): QuotationVersion
    {
        return DB::transaction(function () use ($actor, $quotation): QuotationVersion {
            $quotation = Quotation::query()->with('currentVersion')->lockForUpdate()->findOrFail($quotation->id);
            $current = $quotation->currentVersion;
            if ($current?->status === QuotationVersionStatus::Draft) {
                throw ValidationException::withMessages(['quotation' => 'Finish or issue the existing quotation draft before creating another revision.']);
            }
            if ($current === null) {
                throw ValidationException::withMessages(['quotation' => 'This quotation has no version to revise.']);
            }
            $next = QuotationVersion::create([
                'quotation_id' => $quotation->id,
                'version_number' => ((int) QuotationVersion::query()->where('quotation_id', $quotation->id)->max('version_number')) + 1,
                'status' => QuotationVersionStatus::Draft,
                'line_snapshots' => $current->line_snapshots,
                'subtotal_minor' => $current->subtotal_minor,
                'adjustment_minor' => 0,
                'total_minor' => $current->subtotal_minor,
                'currency' => $current->currency,
                'created_by' => $actor->id,
            ]);
            $quotation->update(['current_version_id' => $next->id, 'status' => QuotationStatus::Draft]);
            $this->audit->record('quotation.revision_created', $actor, $quotation, ['current_version_id' => $current->id], ['current_version_id' => $next->id, 'version_number' => $next->version_number]);

            return $next;
        });
    }
}
