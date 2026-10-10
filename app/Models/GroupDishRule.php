<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupDishRule extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'group_id';

    protected $fillable = ['group_id', 'dish_id', 'effect'];

    /** @return BelongsTo<SelectionGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(SelectionGroup::class, 'group_id');
    }

    /** @return BelongsTo<Dish, $this> */
    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class, 'dish_id');
    }
}
