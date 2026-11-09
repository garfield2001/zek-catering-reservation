<?php

namespace App\Enums;

enum ReservationChangeRequestStatus: string
{
    case PendingReview = 'pending_review';
    case AwaitingCustomerAgreement = 'awaiting_customer_agreement';
    case AwaitingReconfirmation = 'awaiting_reconfirmation';
    case Applied = 'applied';
    case Declined = 'declined';
}
