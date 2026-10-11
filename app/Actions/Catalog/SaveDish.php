<?php

namespace App\Actions\Catalog;

use App\Models\Dish;
use App\Models\DishCategory;
use App\Models\ProductFamily;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveDish
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(User $actor, array $data, ?Dish $dish = null): Dish
    {
        return DB::transaction(function () use ($actor, $data, $dish) {
            $category = DishCategory::lockForUpdate()->findOrFail($data['category_id']);
            if ($data['active'] && ! $category->active) {
                throw ValidationException::withMessages(['category_id' => 'Activate the category before making this food item available.']);
            }
            $families = ProductFamily::whereIn('id', $data['family_ids'])->orderBy('id')->lockForUpdate()->get();
            if ($families->count() !== count($data['family_ids']) || $families->contains('active', false)) {
                throw ValidationException::withMessages(['family_ids' => 'Select only active collections.']);
            }
            $dish = $dish ? Dish::lockForUpdate()->findOrFail($dish->id) : new Dish;
            Gate::forUser($actor->fresh())->authorize($dish->exists ? 'update' : 'create', $dish->exists ? $dish : Dish::class);
            $before = $dish->exists ? $this->snapshot($dish) : null;
            $dish->fill(collect($data)->except(['family_ids', 'code'])->all());
            if (! $dish->exists) {
                $dish->code = $data['code'] ?? 'item_'.strtolower((string) Str::ulid());
            }
            $dish->save();
            $this->syncEligibility($dish, $data['family_ids']);
            $dish->unsetRelation('eligibleFamilies');
            $this->audit->record($before ? 'catalog.dish_updated' : 'catalog.dish_created', $actor, $dish, $before, $this->snapshot($dish));

            return $dish;
        });
    }

    private function syncEligibility(Dish $dish, array $familyIds): void
    {
        DB::table('dish_family_eligibilities')->where('dish_id', $dish->id)
            ->whereNotIn('family_id', $familyIds)->update(['active' => false, 'updated_at' => now()]);
        foreach ($familyIds as $familyId) {
            DB::table('dish_family_eligibilities')->upsert([
                ['dish_id' => $dish->id, 'family_id' => $familyId, 'active' => true,
                    'created_at' => now(), 'updated_at' => now()],
            ], ['dish_id', 'family_id'], ['active', 'updated_at']);
        }
    }

    private function snapshot(Dish $dish): array
    {
        return [...$dish->only('code', 'name', 'description', 'category_id', 'active'),
            'eligible_family_ids' => DB::table('dish_family_eligibilities')->where('dish_id', $dish->id)
                ->where('active', true)->orderBy('family_id')->pluck('family_id')->all()];
    }
}
