<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $code
 * @property string $description
 * @property-read PermissionOverride $pivot
 */
class Permission extends Model
{
    protected $fillable = ['code', 'description'];
}
