<?php

namespace App\Actions\Catalog;

use App\Models\DishCategory;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class SaveCategory
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(User $actor, array $data, ?DishCategory $category = null): DishCategory
    {
        return DB::transaction(function () use ($actor, $data, $category) {
            if (! $category) {
                Role::where('code', 'admin')->lockForUpdate()->firstOrFail();
            }
            $category = $category ? DishCategory::lockForUpdate()->findOrFail($category->id) : new DishCategory;
            Gate::forUser($actor->fresh())->authorize($category->exists ? 'update' : 'create', $category->exists ? $category : DishCategory::class);
            $before = $category->exists ? $category->only('code', 'name', 'sort_order', 'active') : null;
            $category->fill(collect($data)->except(['code', 'sort_order'])->all());
            if (! $category->exists) {
                $category->code = $data['code'] ?? 'category_'.strtolower((string) Str::ulid());
                $category->sort_order = (int) DishCategory::max('sort_order') + 1;
            }
            $category->save();
            $this->audit->record($before ? 'catalog.category_updated' : 'catalog.category_created', $actor, $category,
                $before, $category->only('code', 'name', 'sort_order', 'active'));

            return $category;
        });
    }
}
