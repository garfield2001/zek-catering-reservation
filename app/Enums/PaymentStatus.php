<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case PendingVerification = 'pending_verification';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Reversed = 'reversed';
}
