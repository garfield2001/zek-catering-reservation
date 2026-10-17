<?php

namespace App\Services\Package;

use App\Models\Dish;
use App\Models\PackageVersion;
use App\Services\Catalog\CatalogAvailability;
use App\Services\Catalog\MinorMoney;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Shared distinct-food menu rules; measurements and paid substitutions are add-on-only. */
class PackageMenuConfiguration
{
    public function __construct(private CatalogAvailability $availability) {}

    public function validateDraft(PackageVersion $version): void
    {
        app(PackageFoodSizes::class)->validateDraft($version);
        app(PackageServingDefaults::class)->validateDraft($version);
        $version->load(['product.family', 'foodItems.category', 'requiredCategories']);
        if (! $version->product?->family?->behavior()->supportsPackageDrafts()) {
            return;
        }
        $total = (int) $version->total_selection_count;
        if ($total < 1) {
            throw ValidationException::withMessages(['total_selection_count' => 'Set the total number of distinct Food Items included in this package.']);
        }
        $rows = $version->foodItems;
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['food_items' => 'Add at least one active Food Item to the package menu.']);
        }
        $family = $version->product->family;
        $eligibleIds = $this->availability->eligibleDishIds($family, $rows->pluck('id')->all());
        foreach ($rows as $dish) {
            if (! isset($eligibleIds[$dish->id])) {
                throw ValidationException::withMessages(['food_items' => "{$dish->name} is not currently available for {$family->name}."]);
            }
        }
        $fixed = $rows->where('pivot.is_fixed', true);
        $requiredIds = $version->requiredCategories->pluck('id')->all();
        $grouped = $rows->groupBy('category_id');
        foreach ($requiredIds as $categoryId) {
            $count = $grouped->get($categoryId, collect())->count();
            if ($count < 1) {
                throw ValidationException::withMessages(['required_categories' => 'Each required Food Category needs at least one allowed Food Item.']);
            }
        }
        $singleRequired = collect($requiredIds)->filter(fn ($id) => $grouped->get($id, collect())->count() === 1)
            ->map(fn ($id) => $grouped[$id]->first()->id)->all();
        $fixedIds = $fixed->pluck('id')->all();
        if (array_diff($singleRequired, $fixedIds) !== []) {
            throw ValidationException::withMessages(['food_items' => 'A required category with one allowed Food Item must be a fixed inclusion.']);
        }
        if (count($fixedIds) > $total) {
            throw ValidationException::withMessages(['total_selection_count' => 'The fixed inclusions exceed the package selection total.']);
        }
        if ($rows->count() < $total) {
            throw ValidationException::withMessages(['food_items' => "Allow at least {$total} distinct Food Items for this package."]);
        }
        // Every required category contributes at least one distinct Food Item.
        // A category with one allowed item is also represented by its fixed inclusion,
        // while categories with multiple allowed items still require one customer choice.
        $minimum = count($fixedIds) + collect($requiredIds)->filter(fn ($id) => ! $fixed->contains('category_id', $id))->count();
        if ($minimum > $total) {
            throw ValidationException::withMessages(['total_selection_count' => 'Required category selections exceed the package selection total.']);
        }
    }

    public function validateCustomerSelection(PackageVersion $version, array $dishIds): array
    {
        foreach ($dishIds as $id) {
            if ((! is_int($id) && (! is_string($id) || ! ctype_digit($id))) || (int) $id < 1) {
                throw ValidationException::withMessages(['food_items' => 'Select valid Food Items.']);
            }
        }
        if ($version->status === 'published') {
            return $this->validatePublishedSelection($version, $dishIds);
        }
        $this->validateDraft($version);
        $ids = array_values(array_unique(array_map('intval', $dishIds)));
        if (count($ids) !== count($dishIds) || count($ids) !== (int) $version->total_selection_count) {
            throw ValidationException::withMessages(['food_items' => "Select exactly {$version->total_selection_count} different Food Items."]);
        }
        $allowed = $version->foodItems()->whereIn('dishes.id', $ids)->get();
        if ($allowed->count() !== count($ids)) {
            throw ValidationException::withMessages(['food_items' => 'One or more selected Food Items is not available for this package.']);
        }
        if ($version->foodItems->where('pivot.is_fixed', true)->pluck('id')->diff($ids)->isNotEmpty()) {
            throw ValidationException::withMessages(['food_items' => 'Keep every fixed inclusion in the selected menu.']);
        }
        foreach ($version->requiredCategories as $category) {
            if (! $allowed->contains(fn ($dish) => $dish->category_id === $category->id)) {
                throw ValidationException::withMessages(['food_items' => "Select at least one Food Item from {$category->name}."]);
            }
        }

        return $allowed->pluck('id')->all();
    }

    public function validatePublishedAvailability(PackageVersion $version): void
    {
        $menu = $version->snapshot()['menu'] ?? null;
        if (! is_array($menu)) {
            throw ValidationException::withMessages(['food_items' => 'This publication has no saved menu.']);
        }
        $items = collect($menu['food_items']);
        $family = $version->family;
        $live = $this->availability->eligibleDishIds($family, $items->pluck('id')->all());
        $categories = DB::table('dish_categories')->whereIn('id', $items->pluck('category_id'))->where('active', true)->pluck('id')->all();
        $available = $items->filter(fn (array $item): bool => isset($live[$item['id']]) && in_array($item['category_id'], $categories, true));
        if ($available->count() < $menu['total_selection_count'] || $items->where('is_fixed', true)->pluck('id')->diff($available->pluck('id'))->isNotEmpty()) {
            throw ValidationException::withMessages(['food_items' => 'The published menu does not have enough available foods or a fixed inclusion is unavailable.']);
        }
        foreach ($menu['required_categories'] as $category) {
            if (! $available->contains('category_id', $category['id'])) {
                throw ValidationException::withMessages(['food_items' => "No available choice remains for {$category['name']}."]);
            }
        }
    }

    private function validatePublishedSelection(PackageVersion $version, array $dishIds): array
    {
        $this->validatePublishedAvailability($version);
        $menu = $version->snapshot()['menu'];
        $ids = array_map('intval', $dishIds);
        $items = collect($menu['food_items'])->whereIn('id', $ids);
        if (count($ids) !== count(array_unique($ids)) || count($ids) !== $menu['total_selection_count'] || $items->count() !== count($ids)) {
            throw ValidationException::withMessages(['food_items' => "Select exactly {$menu['total_selection_count']} different permitted foods."]);
        }
        if (collect($menu['food_items'])->where('is_fixed', true)->pluck('id')->diff($ids)->isNotEmpty()) {
            throw ValidationException::withMessages(['food_items' => 'Keep every fixed inclusion in the selected menu.']);
        }
        foreach ($menu['required_categories'] as $category) {
            if (! $items->contains('category_id', $category['id'])) {
                throw ValidationException::withMessages(['food_items' => "Select at least one food from {$category['name']}."]);
            }
        }
        $liveIds = $this->availability->eligibleDishIds($version->family, $ids);
        $activeCategoryIds = DB::table('dish_categories')->whereIn('id', $items->pluck('category_id'))->where('active', true)->pluck('id')->all();
        foreach (Dish::whereIn('id', $ids)->get() as $dish) {
            $frozenItem = $items->firstWhere('id', $dish->id);
            if (! isset($liveIds[$dish->id]) || ! in_array($frozenItem['category_id'], $activeCategoryIds, true)) {
                throw ValidationException::withMessages(['food_items' => "{$dish->name} is currently unavailable."]);
            }
        }

        return $ids;
    }

    public function replace(PackageVersion $version, array $foodItemIds, array $requiredCategoryIds, array $details = []): void
    {
        $this->validateIds($version, $foodItemIds, $requiredCategoryIds);
        DB::transaction(function () use ($version, $foodItemIds, $requiredCategoryIds, $details): void {
            DB::table('package_version_food_items')->where('package_version_id', $version->id)->delete();
            $rows = [];
            foreach (array_values(array_unique(array_map('intval', $foodItemIds))) as $id) {
                $rows[] = ['package_version_id' => $version->id, 'dish_id' => $id, ...$this->foodDetails($version, $details[$id] ?? []), 'created_at' => now(), 'updated_at' => now()];
            }
            DB::table('package_version_food_items')->insert($rows);
            DB::table('package_version_required_categories')->where('package_version_id', $version->id)->delete();
            foreach (array_values(array_unique(array_map('intval', $requiredCategoryIds))) as $id) {
                DB::table('package_version_required_categories')->insert(['package_version_id' => $version->id, 'category_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            }
            $this->promoteSingleRequiredItems($version);
        });
    }

    private function foodDetails(PackageVersion $version, array $data): array
    {
        $behavior = $version->family->behavior_type;
        $fixed = (bool) ($data['is_fixed'] ?? false);
        $quantity = $data['included_quantity'] ?? null;
        $unit = $data['included_unit'] ?? null;
        $amount = app(MinorMoney::class)->fromPesos($data['price_adjustment_pesos'] ?? '0', 'food_details');
        if ($behavior === 'main_package' && ($quantity !== null || $unit !== null || $amount !== 0)) {
            throw ValidationException::withMessages(['food_details' => 'Catering foods have equal price standing and no per-food quantities.']);
        }
        if (($quantity === null) !== ($unit === null) || ($quantity !== null && (! is_numeric($quantity) || $quantity <= 0 || ! in_array($unit, ['kg', 'tray', 'portion', 'piece', 'bottle', 'pack'], true)))) {
            throw ValidationException::withMessages(['food_details' => 'A measurement needs a positive quantity and a supported unit.']);
        }
        if ($behavior === 'fixed_bundle' && $amount !== 0) {
            throw ValidationException::withMessages(['food_details' => 'Fixed bundles cannot have food substitutions or premiums.']);
        }

        return ['is_fixed' => $behavior === 'fixed_bundle' || $fixed, 'always_include' => $fixed, 'included_quantity' => $quantity,
            'included_unit' => $unit, 'price_adjustment_minor' => $amount];
    }

    private function validateIds(PackageVersion $version, array $foodItemIds, array $requiredCategoryIds): void
    {
        $family = $version->product()->with('family')->firstOrFail()->family;
        $ids = array_values(array_unique(array_map('intval', $foodItemIds)));
        if ($ids === [] || count($ids) !== count($foodItemIds)) {
            throw ValidationException::withMessages(['food_items' => 'Food Items must be selected without duplicates.']);
        }
        $eligible = count($this->availability->eligibleDishIds($family, $ids));
        if ($eligible !== count($ids)) {
            throw ValidationException::withMessages(['food_items' => 'Every selected Food Item must be active, with an active category and service.']);
        }
        if (count($ids) < (int) $version->total_selection_count) {
            throw ValidationException::withMessages(['food_items' => 'The package selection total cannot exceed the allowed Food Items.']);
        }
        $categories = DB::table('dish_categories')->whereIn('id', $requiredCategoryIds)->where('active', true)->count();
        if ($categories !== count(array_unique($requiredCategoryIds))) {
            throw ValidationException::withMessages(['required_categories' => 'Required Food Categories must be active.']);
        }
    }

    private function promoteSingleRequiredItems(PackageVersion $version): void
    {
        $rows = DB::table('package_version_food_items as p')->join('dishes as d', 'd.id', '=', 'p.dish_id')->where('p.package_version_id', $version->id)->get(['p.dish_id', 'd.category_id']);
        $required = DB::table('package_version_required_categories')->where('package_version_id', $version->id)->pluck('category_id');
        foreach ($required as $categoryId) {
            $matches = $rows->where('category_id', $categoryId);
            if ($matches->count() === 1) {
                DB::table('package_version_food_items')->where(['package_version_id' => $version->id, 'dish_id' => $matches->first()->dish_id])->update(['is_fixed' => true, 'updated_at' => now()]);
            }
        }
    }
}
