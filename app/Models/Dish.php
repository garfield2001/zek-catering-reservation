<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dish extends Model
{
    protected $fillable = ['code', 'name', 'description', 'category_id', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DishCategory::class, 'category_id');
    }

    public function eligibleFamilies(): BelongsToMany
    {
        return $this->belongsToMany(ProductFamily::class, 'dish_family_eligibilities', 'dish_id', 'family_id')
            ->withPivot('active')->withTimestamps();
    }

    public function offerings(): HasMany
    {
        return $this->hasMany(DishOffering::class);
    }
}
