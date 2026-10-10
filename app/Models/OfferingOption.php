<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferingOption extends Model
{
    protected $fillable = ['offering_id', 'option_code', 'label', 'unit', 'quantity', 'serving_pax', 'serving_claim', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'quantity' => 'decimal:3', 'serving_pax' => 'integer'];
    }

    /** @return BelongsTo<DishOffering, $this> */
    public function offering(): BelongsTo
    {
        return $this->belongsTo(DishOffering::class);
    }
}
