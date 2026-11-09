<?php

namespace App\Actions\ReservationRequest;

use App\Enums\ReservationRequestStatus;
use App\Models\ReservationRequest;
use App\Models\ReservationRequestStatusEvent;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateReservationRequestStatus
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(User $actor, ReservationRequest $request, ReservationRequestStatus $next): ReservationRequest
    {
        return DB::transaction(function () use ($actor, $request, $next): ReservationRequest {
            $locked = ReservationRequest::query()->lockForUpdate()->findOrFail($request->id);
            $current = $locked->status;
            if (! in_array($next, $current->transitions(), true)) {
                throw ValidationException::withMessages(['status' => "{$current->label()} requests cannot move to {$next->label()}."]);
            }
            $locked->update(['status' => $next]);
            ReservationRequestStatusEvent::create(['reservation_request_id' => $locked->id, 'from_status' => $current, 'to_status' => $next, 'changed_by' => $actor->id]);
            $this->audit->record('reservation_request.status_updated', $actor, $locked, ['status' => $current->value], ['reference' => $locked->reference, 'status' => $next->value]);

            return $locked;
        });
    }
}
