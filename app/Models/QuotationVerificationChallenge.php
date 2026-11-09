<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property int $quotation_version_id @property int $attempts @property CarbonImmutable $expires_at @property CarbonImmutable|null $consumed_at @property CarbonImmutable|null $verified_until */
class QuotationVerificationChallenge extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime', 'consumed_at' => 'immutable_datetime', 'verified_until' => 'immutable_datetime'];
    }

    /** @return BelongsTo<QuotationVersion, $this> */
    public function quotationVersion(): BelongsTo
    {
        return $this->belongsTo(QuotationVersion::class);
    }
}
