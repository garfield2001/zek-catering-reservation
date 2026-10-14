<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SelectionGroup extends Model
{
    protected $fillable = ['package_version_id', 'group_code', 'name', 'mode', 'slot_kind', 'minimum_count', 'maximum_count',
        'unique_dishes', 'category_mode', 'inclusion_count', 'sort_order'];

    protected function casts(): array
    {
        return ['minimum_count' => 'integer', 'maximum_count' => 'integer', 'unique_dishes' => 'boolean',
            'inclusion_count' => 'decimal:3', 'sort_order' => 'integer'];
    }

    /** @return BelongsTo<PackageVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(PackageVersion::class, 'package_version_id');
    }

    /** @return HasMany<GroupOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(GroupOption::class, 'group_id');
    }

    /** @return HasMany<GroupCategoryRule, $this> */
    public function categoryRules(): HasMany
    {
        return $this->hasMany(GroupCategoryRule::class, 'group_id');
    }

    /** @return HasMany<GroupDishRule, $this> */
    public function dishRules(): HasMany
    {
        return $this->hasMany(GroupDishRule::class, 'group_id');
    }
}
