<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property string $status @property string $requested_adjustment_minor */
class QuotationAdjustmentRequest extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['requested_adjustment_minor' => 'string', 'reviewed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<QuotationVersion, $this> */
    public function quotationVersion(): BelongsTo
    {
        return $this->belongsTo(QuotationVersion::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
