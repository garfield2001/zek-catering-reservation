<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/** @property string $effect */
class PermissionOverride extends Pivot
{
    public $timestamps = false;
}
