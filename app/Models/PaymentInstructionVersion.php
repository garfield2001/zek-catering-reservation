<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $business_policy_id
 * @property int $version_number
 * @property CarbonImmutable $effective_at
 * @property CarbonImmutable $created_at
 * @property-read User|null $creator
 */
class PaymentInstructionVersion extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['effective_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<BusinessPolicy, $this> */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(BusinessPolicy::class, 'business_policy_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return array<string, string|int> */
    public function snapshot(): array
    {
        return ['id' => $this->id, 'version_number' => $this->version_number, 'account_name' => $this->account_name, 'ewallet_number' => $this->ewallet_number, 'bank_account' => $this->bank_account, 'additional_instructions' => $this->additional_instructions, 'effective_at' => $this->effective_at->toIso8601String()];
    }
}
