<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditEvent extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['before_data' => 'array', 'after_data' => 'array', 'context' => 'array', 'created_at' => 'immutable_datetime'];
    }
}
