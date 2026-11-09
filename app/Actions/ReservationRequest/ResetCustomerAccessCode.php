<?php

namespace App\Actions\ReservationRequest;

use App\Models\ReservationRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\ReservationRequest\CustomerAccessCode;
use Illuminate\Support\Facades\DB;

class ResetCustomerAccessCode
{
    public function __construct(private CustomerAccessCode $codes, private AuditLogger $audit) {}

    public function handle(User $actor, ReservationRequest $request): string
    {
        return DB::transaction(function () use ($actor, $request): string {
            $request = ReservationRequest::query()->lockForUpdate()->findOrFail($request->id);
            $code = $this->codes->generate();
            $request->forceFill([
                'customer_access_code_hash' => $this->codes->hash($code),
                'customer_access_code_reset_at' => now(),
                'customer_access_code_reset_by' => $actor->id,
            ])->save();
            $this->audit->record('reservation_request.customer_access_code_reset', $actor, $request, null, [
                'reference' => $request->reference,
            ]);

            return $code;
        });
    }
}
