<?php

namespace App\Actions\Cancellations;

use App\Enums\CancellationRequestStatus;
use App\Enums\RefundStatus;
use App\Models\Refund;
use App\Models\RefundEvent;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompleteRefund
{
    public function __construct(private AuditLogger $audit) {}

    /** @param array{evidence_disk?: string|null, evidence_path?: string|null, evidence_sha256?: string|null, note?: string|null} $data */
    public function handle(User $actor, Refund $attempt, array $data): Refund
    {
        if (! $actor->active || ! $actor->isAdmin()) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($actor, $attempt, $data): Refund {
            $refund = Refund::query()->with(['payment', 'cancellationRequest'])->lockForUpdate()->findOrFail($attempt->id);
            if ($refund->status !== RefundStatus::Approved) {
                throw ValidationException::withMessages(['refund' => 'Only an approved refund can be marked completed after the money is actually returned.']);
            }
            $refund->payment()->lockForUpdate()->firstOrFail();
            $refund->forceFill([
                'status' => RefundStatus::Completed, 'completed_by' => $actor->id, 'completed_at' => now(),
                'evidence_disk' => $data['evidence_disk'] ?? null, 'evidence_path' => $data['evidence_path'] ?? null,
                'evidence_sha256' => $data['evidence_sha256'] ?? null,
            ])->save();
            RefundEvent::create(['refund_id' => $refund->id, 'from_status' => RefundStatus::Approved, 'to_status' => RefundStatus::Completed, 'note' => filled($data['note'] ?? null) ? trim((string) $data['note']) : null, 'actor_id' => $actor->id]);
            $cancellation = $refund->cancellationRequest;
            if (! $cancellation->refunds()->where('status', RefundStatus::PendingReview)->exists() && ! $cancellation->refunds()->where('status', RefundStatus::Approved)->exists()) {
                $before = $cancellation->status;
                $cancellation->forceFill(['status' => CancellationRequestStatus::RefundCompleted])->save();
                $cancellation->events()->create(['from_status' => $before, 'to_status' => CancellationRequestStatus::RefundCompleted, 'note' => 'A refund was recorded as completed.', 'actor_id' => $actor->id]);
            }
            $this->audit->record('refund.completed', $actor, $refund, ['status' => 'approved'], ['status' => 'completed', 'amount_minor' => $refund->amount_minor]);

            return $refund->fresh();
        });
    }
}
