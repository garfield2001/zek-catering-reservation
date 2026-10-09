<?php

namespace App\Enums\Catalog;

enum ServingUnit: string
{
    case Kilogram = 'kg';
    case Tray = 'tray';
    case Portion = 'portion';
    case Piece = 'piece';
    case Bottle = 'bottle';
    case Pack = 'pack';
}
