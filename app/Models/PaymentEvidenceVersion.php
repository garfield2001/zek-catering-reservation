<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentEvidenceVersion extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['disk', 'path', 'sha256'];

    protected function casts(): array
    {
        return ['submitted_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
