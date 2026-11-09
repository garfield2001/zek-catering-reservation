<?php

namespace App\Enums;

enum QuotationVersionStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Accepted = 'accepted';
    case Withdrawn = 'withdrawn';
    case Superseded = 'superseded';
    case Expired = 'expired';
}
