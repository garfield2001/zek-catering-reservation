<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CarbonImmutable $accepted_at
 * @property CarbonImmutable $payment_due_at
 * @property CarbonImmutable|null $final_payment_due_at
 * @property CarbonImmutable $menu_finalization_due_at
 */
class QuotationAcceptance extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['evidence_disk', 'evidence_path'];

    protected function casts(): array
    {
        return ['accepted_at' => 'immutable_datetime', 'payment_due_at' => 'immutable_datetime', 'final_payment_due_at' => 'immutable_datetime', 'menu_finalization_due_at' => 'immutable_datetime', 'terms_snapshot' => 'array'];
    }

    /** @return BelongsTo<QuotationVersion, $this> */
    public function quotationVersion(): BelongsTo
    {
        return $this->belongsTo(QuotationVersion::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
