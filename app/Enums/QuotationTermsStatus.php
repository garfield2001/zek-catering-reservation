<?php

namespace App\Enums;

enum QuotationTermsStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Superseded = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Approved => 'Approved',
            self::Superseded => 'Superseded',
        };
    }
}
