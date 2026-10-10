<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupOption extends Model
{
    protected $fillable = ['group_id', 'offering_option_id', 'is_default', 'price_adjustment_minor'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    /** @return BelongsTo<SelectionGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(SelectionGroup::class, 'group_id');
    }

    /** @return BelongsTo<OfferingOption, $this> */
    public function offeringOption(): BelongsTo
    {
        return $this->belongsTo(OfferingOption::class, 'offering_option_id');
    }
}
