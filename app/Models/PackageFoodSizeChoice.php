<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackageFoodSizeChoice extends Model
{
    protected $fillable = ['size_group_id', 'quantity', 'size_label', 'extra_price_minor', 'is_default'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'is_default' => 'boolean'];
    }
}
