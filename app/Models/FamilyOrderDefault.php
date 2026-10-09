<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyOrderDefault extends Model
{
    protected $primaryKey = 'family_id';

    public $incrementing = false;

    protected $fillable = ['minimum_quantity', 'quantity_step', 'maximum_quantity', 'enforce_minimum_floor'];

    protected function casts(): array
    {
        return ['minimum_quantity' => 'integer', 'quantity_step' => 'integer',
            'maximum_quantity' => 'integer', 'enforce_minimum_floor' => 'boolean'];
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(ProductFamily::class, 'family_id');
    }
}
