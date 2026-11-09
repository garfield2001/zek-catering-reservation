<?php

namespace App\Actions\Quotation;

use App\Enums\QuotationStatus;
use App\Enums\QuotationVersionStatus;
use App\Models\QuotationAcceptance;
use App\Models\QuotationVersion;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordQuotationAcceptance
{
    public function __construct(private AuditLogger $audit) {}

    public function online(QuotationVersion $version, string $acceptedByName, string $verificationReference): QuotationAcceptance
    {
        return $this->record($version, $acceptedByName, 'online', null, $verificationReference);
    }

    public function offline(QuotationVersion $version, User $actor, string $acceptedByName, ?string $evidenceDisk = null, ?string $evidencePath = null): QuotationAcceptance
    {
        return $this->record($version, $acceptedByName, 'offline', $actor, null, $evidenceDisk, $evidencePath);
    }

    private function record(QuotationVersion $version, string $acceptedByName, string $method, ?User $actor, ?string $verificationReference, ?string $evidenceDisk = null, ?string $evidencePath = null): QuotationAcceptance
    {
        return DB::transaction(function () use ($version, $acceptedByName, $method, $actor, $verificationReference, $evidenceDisk, $evidencePath): QuotationAcceptance {
            $version = QuotationVersion::query()->with('quotation')->lockForUpdate()->findOrFail($version->id);
            if ($version->status !== QuotationVersionStatus::Issued || $version->valid_until?->isPast()) {
                throw ValidationException::withMessages(['quotation' => 'Only a current, unexpired issued quotation can be accepted.']);
            }
            if (QuotationAcceptance::query()->where('quotation_version_id', $version->id)->exists()) {
                throw ValidationException::withMessages(['quotation' => 'This quotation version has already been accepted.']);
            }
            $acceptedAt = CarbonImmutable::now();
            $terms = $version->terms_snapshot;
            $deadlineHours = (int) ($terms['reservation_payment']['payment_deadline_hours'] ?? 0);
            $menuDays = (int) ($terms['menu_finalization']['days_after_acceptance'] ?? 0);
            if ($deadlineHours < 1 || $menuDays < 1) {
                throw ValidationException::withMessages(['quotation' => 'The issued quotation has an invalid immutable terms snapshot.']);
            }
            $acceptance = QuotationAcceptance::create([
                'quotation_version_id' => $version->id, 'method' => $method, 'accepted_by_name' => trim($acceptedByName), 'accepted_at' => $acceptedAt,
                'payment_due_at' => $acceptedAt->addHours($deadlineHours),
                'final_payment_due_at' => $this->finalPaymentDueAt($version, $terms),
                'menu_finalization_due_at' => $acceptedAt->addDays($menuDays),
                'terms_snapshot' => $terms, 'verification_reference' => $verificationReference,
                'evidence_disk' => $evidenceDisk, 'evidence_path' => $evidencePath, 'recorded_by' => $actor?->id,
            ]);
            $version->update(['status' => QuotationVersionStatus::Accepted]);
            $version->quotation->update(['status' => QuotationStatus::Accepted, 'current_version_id' => $version->id]);
            $this->audit->record('quotation.accepted_'.$method, $actor, $version, ['status' => 'issued'], ['acceptance_id' => $acceptance->id]);

            return $acceptance;
        });
    }

    /** @param array<string, mixed>|null $terms */
    private function finalPaymentDueAt(QuotationVersion $version, ?array $terms): ?CarbonImmutable
    {
        $days = $terms['final_payment_days_before_event'] ?? null;
        $eventDate = $version->quotation->event_date;
        if (! is_int($days) && ! is_numeric($days)) {
            return null;
        }

        return $eventDate === null ? null : CarbonImmutable::parse($eventDate->toDateString(), 'Asia/Manila')->subDays((int) $days);
    }
}
