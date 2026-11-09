<?php

namespace App\Actions\Reservations;

use App\Actions\Quotation\RecordQuotationAcceptance;
use App\Enums\ReservationChangeRequestStatus;
use App\Models\ReservationChangeRequest;
use App\Models\ReservationChangeRequestEvent;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AgreeReservationChangeQuotation
{
    public function __construct(private RecordQuotationAcceptance $acceptance, private AuditLogger $audit) {}

    public function handle(ReservationChangeRequest $attempt, string $customerName): ReservationChangeRequest
    {
        return DB::transaction(function () use ($attempt, $customerName): ReservationChangeRequest {
            $change = ReservationChangeRequest::query()->with('quotationVersion')->lockForUpdate()->findOrFail($attempt->id);
            if ($change->status !== ReservationChangeRequestStatus::AwaitingCustomerAgreement || $change->quotationVersion === null) {
                throw ValidationException::withMessages(['change_request' => 'This reservation change is not awaiting a revised quotation agreement.']);
            }
            $this->acceptance->online($change->quotationVersion, trim($customerName), 'reservation-change-'.$change->id);
            $change->forceFill(['status' => ReservationChangeRequestStatus::AwaitingReconfirmation])->save();
            ReservationChangeRequestEvent::create([
                'reservation_change_request_id' => $change->id,
                'from_status' => ReservationChangeRequestStatus::AwaitingCustomerAgreement->value,
                'to_status' => ReservationChangeRequestStatus::AwaitingReconfirmation->value,
                'note' => 'Customer agreed to the revised quotation.',
                'actor_id' => null,
            ]);
            $this->audit->record('reservation.change_customer_agreed', null, $change, ['status' => 'awaiting_customer_agreement'], ['status' => 'awaiting_reconfirmation', 'quotation_version_id' => $change->quotation_version_id]);

            return $change;
        });
    }
}
