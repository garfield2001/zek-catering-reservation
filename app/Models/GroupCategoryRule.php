<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupCategoryRule extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'group_id';

    protected $fillable = ['group_id', 'category_id', 'minimum_count', 'maximum_count'];

    /** @return BelongsTo<SelectionGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(SelectionGroup::class, 'group_id');
    }

    /** @return BelongsTo<DishCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(DishCategory::class, 'category_id');
    }
}
