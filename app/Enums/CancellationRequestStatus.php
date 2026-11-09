<?php

namespace App\Enums;

enum CancellationRequestStatus: string
{
    case PendingReview = 'pending_review';
    case Declined = 'declined';
    case Cancelled = 'cancelled';
    case RefundUnderReview = 'refund_under_review';
    case RefundApproved = 'refund_approved';
    case RefundCompleted = 'refund_completed';

    public function customerLabel(): string
    {
        return match ($this) {
            self::PendingReview => 'Cancellation Requested',
            self::Declined => 'Cancellation Declined',
            self::Cancelled => 'Cancelled',
            self::RefundUnderReview => 'Refund Under Review',
            self::RefundApproved => 'Refund Approved',
            self::RefundCompleted => 'Refund Completed',
        };
    }
}
