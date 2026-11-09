<?php

namespace App\Enums;

enum RefundStatus: string
{
    case PendingReview = 'pending_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Completed = 'completed';
}
