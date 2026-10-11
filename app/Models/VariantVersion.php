<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VariantVersion extends Model
{
    protected $fillable = ['variant_id', 'family_id', 'version_number', 'status', 'draft_revision', 'base_price_minor',
        'currency', 'price_basis', 'selection_policy', 'minimum_override', 'step_override', 'maximum_override',
        'maximum_override_enabled', 'minimum_quantity', 'quantity_step', 'maximum_quantity',
        'subgroup_minimum', 'subgroup_step', 'subgroup_maximum', 'created_by', 'cloned_from_id',
        'published_snapshot', 'published_at', 'published_by', 'abandoned_at', 'abandoned_by'];

    protected function casts(): array
    {
        return ['draft_revision' => 'integer', 'minimum_override' => 'integer', 'step_override' => 'integer',
            'maximum_override' => 'integer', 'maximum_override_enabled' => 'boolean', 'minimum_quantity' => 'integer',
            'quantity_step' => 'integer', 'maximum_quantity' => 'integer', 'subgroup_minimum' => 'integer',
            'subgroup_step' => 'integer', 'subgroup_maximum' => 'integer', 'published_snapshot' => 'array',
            'published_at' => 'immutable_datetime', 'abandoned_at' => 'immutable_datetime'];
    }

    public function snapshot(): ?array
    {
        $value = $this->getAttribute('published_snapshot');
        if ($value !== null && ! is_array($value)) {
            throw new \LogicException('The published package snapshot is invalid.');
        }

        return $value;
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /** @return BelongsTo<ProductFamily, $this> */
    public function family(): BelongsTo
    {
        return $this->belongsTo(ProductFamily::class, 'family_id');
    }

    /** @return HasMany<SelectionGroup, $this> */
    public function groups(): HasMany
    {
        return $this->hasMany(SelectionGroup::class, 'version_id')->orderBy('sort_order')->orderBy('id');
    }
}
