<?php

namespace App\Enums;

enum QuotationStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Accepted = 'accepted';
    case Withdrawn = 'withdrawn';
    case Expired = 'expired';
}
