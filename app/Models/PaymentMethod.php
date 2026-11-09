<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_offline' => 'boolean', 'is_active' => 'boolean', 'display_order' => 'integer'];
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
