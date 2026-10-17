<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeasurementUnit extends Model
{
    protected $fillable = ['name', 'abbreviation', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
