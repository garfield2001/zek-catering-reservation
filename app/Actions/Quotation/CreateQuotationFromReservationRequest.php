<?php

namespace App\Actions\Quotation;

use App\Enums\QuotationStatus;
use App\Enums\QuotationVersionStatus;
use App\Models\Quotation;
use App\Models\QuotationVersion;
use App\Models\ReservationRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Quotation\QuotationDraftFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateQuotationFromReservationRequest
{
    public function __construct(private QuotationDraftFactory $drafts, private AuditLogger $audit) {}

    public function handle(User $actor, ReservationRequest $request): Quotation
    {
        return DB::transaction(function () use ($actor, $request): Quotation {
            $request = ReservationRequest::query()->with('lines')->lockForUpdate()->findOrFail($request->id);
            $existing = Quotation::query()
                ->where('reservation_request_id', $request->id)
                ->lockForUpdate()
                ->first();
            if ($existing !== null) {
                $existing->setAttribute('existing_case', true);

                return $existing->load('currentVersion');
            }
            $draft = $this->drafts->fromReservationRequest($request);
            $quotation = Quotation::create([
                'reference' => $this->reference(), 'origin' => 'reservation_request', 'reservation_request_id' => $request->id,
                'status' => QuotationStatus::Draft,
                'customer_name' => $request->customer_name, 'customer_mobile' => $request->customer_mobile, 'customer_email' => $request->customer_email,
                'event_date' => $request->event_date, 'event_start_time' => $request->event_start_time,
                'venue_information' => $request->venue_information, 'event_notes' => $request->event_notes,
                'source_snapshot' => ['schema' => 1, 'reservation_request_id' => $request->id, 'reference' => $request->reference, 'commercial_snapshot' => $request->commercial_snapshot],
                'created_by' => $actor->id,
            ]);
            $version = QuotationVersion::create([
                'quotation_id' => $quotation->id, 'version_number' => 1, 'status' => QuotationVersionStatus::Draft,
                'line_snapshots' => $draft['lines'], 'subtotal_minor' => $draft['subtotal_minor'], 'adjustment_minor' => 0, 'total_minor' => $draft['subtotal_minor'],
                'currency' => 'PHP', 'created_by' => $actor->id,
            ]);
            $quotation->update(['current_version_id' => $version->id]);
            $this->audit->record('quotation.created_from_reservation_request', $actor, $quotation, null, [
                'reference' => $quotation->reference, 'reservation_request_id' => $request->id, 'quotation_version_id' => $version->id,
                'total_minor' => (string) $draft['subtotal_minor'],
            ]);

            return $quotation->fresh(['currentVersion']);
        });
    }

    private function reference(): string
    {
        do {
            $reference = 'QT-'.Str::upper(Str::random(12));
        } while (Quotation::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
