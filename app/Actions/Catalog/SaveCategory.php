<?php

namespace App\Actions\Catalog;

use App\Models\DishCategory;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SaveCategory
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(User $actor, array $data, ?DishCategory $category = null): DishCategory
    {
        return DB::transaction(function () use ($actor, $data, $category) {
            $category = $category ? DishCategory::lockForUpdate()->findOrFail($category->id) : new DishCategory;
            Gate::forUser($actor->fresh())->authorize($category->exists ? 'update' : 'create', $category->exists ? $category : DishCategory::class);
            $before = $category->exists ? $category->only('code', 'name', 'sort_order', 'active') : null;
            $category->fill($data);
            $category->save();
            $this->audit->record($before ? 'catalog.category_updated' : 'catalog.category_created', $actor, $category,
                $before, $category->only('code', 'name', 'sort_order', 'active'));

            return $category;
        });
    }
}
