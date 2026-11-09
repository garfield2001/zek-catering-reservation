<?php

namespace App\Actions\Quotation;

use App\Enums\QuotationStatus;
use App\Enums\QuotationVersionStatus;
use App\Models\QuotationVersion;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\BusinessPolicies\CurrentBusinessPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IssueQuotation
{
    public function __construct(private CurrentBusinessPolicy $policies, private AuditLogger $audit) {}

    /** @return array{version: QuotationVersion, public_token: string} */
    public function handle(User $actor, QuotationVersion $version, ?CarbonImmutable $validUntil = null): array
    {
        return DB::transaction(function () use ($actor, $version, $validUntil): array {
            $version = QuotationVersion::query()->with('quotation')->lockForUpdate()->findOrFail($version->id);
            if ($version->status !== QuotationVersionStatus::Draft) {
                throw ValidationException::withMessages(['quotation' => 'Only a quotation draft can be issued.']);
            }
            $policy = $this->policies->current();
            $hasValidityOverride = $validUntil !== null;
            $validUntil ??= CarbonImmutable::now('Asia/Manila')->addDays($policy->quotation_validity_days);
            if ($validUntil->lessThanOrEqualTo(CarbonImmutable::now('Asia/Manila'))) {
                throw ValidationException::withMessages(['valid_until' => 'Choose a quotation validity deadline in the future.']);
            }
            if ($hasValidityOverride && ! $policy->allow_quotation_validity_overrides) {
                throw ValidationException::withMessages(['valid_until' => 'Business Policies do not allow quotation validity overrides.']);
            }
            $token = bin2hex(random_bytes(32));
            QuotationVersion::query()
                ->where('quotation_id', $version->quotation_id)
                ->where('status', QuotationVersionStatus::Issued)
                ->lockForUpdate()
                ->each(fn (QuotationVersion $issued) => $issued->update(['status' => QuotationVersionStatus::Superseded]));
            $version->update([
                'status' => QuotationVersionStatus::Issued,
                'valid_until' => $validUntil,
                'terms_version_id' => null,
                'terms_snapshot' => $policy->quotationSnapshot(),
                'public_token_hash' => hash('sha256', $token),
                'issued_at' => now(),
                'issued_by' => $actor->id,
            ]);
            $version->quotation->update(['status' => QuotationStatus::Issued, 'current_version_id' => $version->id]);
            $this->audit->record('quotation.issued', $actor, $version, ['status' => 'draft'], [
                'quotation_id' => $version->quotation_id, 'version_number' => $version->version_number,
                'business_policy_key' => $policy->key, 'valid_until' => $validUntil->toIso8601String(),
            ]);

            return ['version' => $version->refresh(), 'public_token' => $token];
        });
    }
}
