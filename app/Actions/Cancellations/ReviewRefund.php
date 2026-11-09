<?php

namespace App\Actions\Cancellations;

use App\Enums\CancellationRequestStatus;
use App\Enums\RefundStatus;
use App\Models\CancellationRequest;
use App\Models\Refund;
use App\Models\RefundEvent;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewRefund
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(User $actor, Refund $attempt, bool $approved, string $reviewNote): Refund
    {
        if (! $actor->active || ! $actor->isAdmin()) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($actor, $attempt, $approved, $reviewNote): Refund {
            $refund = Refund::query()->with('cancellationRequest')->lockForUpdate()->findOrFail($attempt->id);
            if ($refund->status !== RefundStatus::PendingReview) {
                throw ValidationException::withMessages(['refund' => 'Only a refund awaiting review can be approved or rejected.']);
            }
            if (! $approved && ! filled($reviewNote)) {
                throw ValidationException::withMessages(['review_note' => 'Explain why this refund was not approved.']);
            }
            $next = $approved ? RefundStatus::Approved : RefundStatus::Rejected;
            $refund->forceFill([
                'status' => $next, 'reviewed_by' => $actor->id, 'reviewed_at' => now(),
                'review_note' => filled($reviewNote) ? trim($reviewNote) : null,
            ])->save();
            RefundEvent::create(['refund_id' => $refund->id, 'from_status' => RefundStatus::PendingReview, 'to_status' => $next, 'note' => $refund->review_note, 'actor_id' => $actor->id]);
            $this->syncCancellation($refund->cancellationRequest, $actor);
            $this->audit->record($approved ? 'refund.approved' : 'refund.rejected', $actor, $refund, ['status' => 'pending_review'], ['status' => $next->value]);

            return $refund->fresh();
        });
    }

    private function syncCancellation(CancellationRequest $cancellation, User $actor): void
    {
        $hasPending = $cancellation->refunds()->where('status', RefundStatus::PendingReview)->exists();
        $hasApproved = $cancellation->refunds()->where('status', RefundStatus::Approved)->exists();
        $hasCompleted = $cancellation->refunds()->where('status', RefundStatus::Completed)->exists();
        $next = $hasPending ? CancellationRequestStatus::RefundUnderReview : ($hasApproved ? CancellationRequestStatus::RefundApproved : ($hasCompleted ? CancellationRequestStatus::RefundCompleted : CancellationRequestStatus::Cancelled));
        if ($cancellation->status === $next) {
            return;
        }
        $before = $cancellation->status;
        $cancellation->forceFill(['status' => $next])->save();
        $cancellation->events()->create(['from_status' => $before, 'to_status' => $next, 'note' => 'Refund review status updated.', 'actor_id' => $actor->id]);
    }
}
