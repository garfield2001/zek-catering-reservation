<?php

namespace App\Enums;

enum ReservationRequestStatus: string
{
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case NeedsInformation = 'needs_information';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::UnderReview => 'Under Review',
            self::NeedsInformation => 'Needs Information',
            self::Closed => 'Closed',
        };
    }

    /** @return list<self> */
    public function transitions(): array
    {
        return match ($this) {
            self::Submitted => [self::UnderReview, self::NeedsInformation, self::Closed],
            self::UnderReview => [self::NeedsInformation, self::Closed],
            self::NeedsInformation => [self::UnderReview, self::Closed],
            self::Closed => [],
        };
    }
}
