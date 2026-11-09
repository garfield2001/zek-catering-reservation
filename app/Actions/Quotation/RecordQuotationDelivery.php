<?php

namespace App\Actions\Quotation;

use App\Enums\QuotationVersionStatus;
use App\Models\QuotationDeliveryEvent;
use App\Models\QuotationVersion;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Validation\ValidationException;

class RecordQuotationDelivery
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(User $actor, QuotationVersion $version, string $channel, ?string $destinationHint, ?string $note): QuotationDeliveryEvent
    {
        if (! in_array($version->status, [QuotationVersionStatus::Issued, QuotationVersionStatus::Accepted], true)) {
            throw ValidationException::withMessages(['delivery' => 'Record delivery only for an issued or accepted quotation.']);
        }
        $event = QuotationDeliveryEvent::create([
            'quotation_version_id' => $version->id, 'channel' => $channel,
            'destination_hint' => filled($destinationHint) ? trim($destinationHint) : null,
            'note' => filled($note) ? trim($note) : null, 'recorded_by' => $actor->id,
        ]);
        $this->audit->record('quotation.delivery_recorded', $actor, $version, null, ['delivery_event_id' => $event->id, 'channel' => $channel]);

        return $event;
    }
}
