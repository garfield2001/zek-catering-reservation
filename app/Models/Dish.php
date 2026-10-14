<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

/** @property-read Pivot $pivot Relationship metadata when loaded through a belongs-to-many association. */
class Dish extends Model
{
    protected $hidden = ['image_disk', 'image_path', 'image_thumbnail_path'];

    protected $appends = ['image_url', 'thumbnail_url'];

    public function getImageUrlAttribute(): ?string
    {
        return $this->getAttribute('image_path') ? route('catalog.images.show', ['dish' => $this->id, 'size' => 'full', 'v' => basename($this->getAttribute('image_path'))], false) : null;
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        return $this->getAttribute('image_thumbnail_path') ? route('catalog.images.show', ['dish' => $this->id, 'size' => 'thumbnail', 'v' => basename($this->getAttribute('image_thumbnail_path'))], false) : null;
    }

    protected $fillable = ['code', 'name', 'description', 'category_id', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    /** @return BelongsTo<DishCategory, $this> */
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
