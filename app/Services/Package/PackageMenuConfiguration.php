<?php

namespace App\Services\Package;

use App\Models\Dish;
use App\Models\MeasurementUnit;
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
        $presentation = app(PackagePresentation::class);
        $presentation->validate($version, $presentation->input($version), $version->foodItems()->pluck('dishes.id')->all());
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
        $rules = $version->requiredCategories->map(fn ($category): array => [
            'id' => $category->id,
            'name' => $category->name,
            'minimum_count' => (int) ($category->pivot->minimum_count ?? 1),
            'maximum_count' => (int) ($category->pivot->maximum_count ?? 1),
            'maximum_count_explicit' => (bool) ($category->pivot->maximum_count_explicit ?? false),
        ])->all();
        if ($version->family->behavior_type === 'fixed_bundle') {
            $rules = [];
        }
        $grouped = $rows->groupBy('category_id');
        $minimum = count($fixed->all());
        foreach ($rules as $rule) {
            $items = $grouped->get($rule['id'], collect());
            $choices = $items->reject(fn ($dish) => $dish->pivot->is_fixed);
            $legacyFixedOnly = $choices->isEmpty() && $items->count() === 1 && (bool) $items->first()->pivot->is_fixed;
            if ($rule['minimum_count'] > $rule['maximum_count']) {
                throw ValidationException::withMessages(['category_choice_rules' => "{$rule['name']} has a minimum greater than its maximum."]);
            }
            if (! $legacyFixedOnly && $rule['maximum_count_explicit'] && $choices->count() < $rule['maximum_count']) {
                throw ValidationException::withMessages(['category_choice_rules' => "{$rule['name']} allows {$choices->count()} customer choice option(s), which is not enough for its maximum of {$rule['maximum_count']}."]);
            }
            if (! $legacyFixedOnly) {
                $minimum += $rule['minimum_count'];
            }
        }
        if ($fixed->count() > $total) {
            throw ValidationException::withMessages(['total_selection_count' => 'The fixed inclusions exceed the package selection total.']);
        }
        if ($rows->count() < $total) {
            throw ValidationException::withMessages(['food_items' => "Allow at least {$total} distinct Food Items for this package."]);
        }
        if ($minimum > $total) {
            throw ValidationException::withMessages(['total_selection_count' => 'Fixed inclusions and required customer choices exceed the package selection total.']);
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
        if ($version->family->behavior_type !== 'fixed_bundle') {
            $this->validateCategorySelections($version->requiredCategories, $version->foodItems, $allowed, $ids);
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
        if ($version->family->behavior_type !== 'fixed_bundle') {
            foreach ($this->snapshotCategoryRules($menu) as $rule) {
                $choices = $available->where('category_id', $rule['id'])->reject(fn (array $item) => $item['is_fixed']);
                $legacyFixedOnly = $choices->isEmpty() && $available->where('category_id', $rule['id'])->where('is_fixed', true)->count() === 1;
                if (! $legacyFixedOnly && $choices->count() < $rule['minimum_count']) {
                    throw ValidationException::withMessages(['food_items' => "No sufficient available customer choices remain for {$rule['name']}. "]);
                }
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
        if ($version->family->behavior_type !== 'fixed_bundle') {
            $this->validateSnapshotCategorySelections($menu, $items, $ids);
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

    public function replace(PackageVersion $version, array $foodItemIds, array $categoryRules, array $details = [], bool $promoteSingleRequiredItems = false): void
    {
        $this->validateIds($version, $foodItemIds, $categoryRules, $details);
        DB::transaction(function () use ($version, $foodItemIds, $categoryRules, $details, $promoteSingleRequiredItems): void {
            DB::table('package_version_food_items')->where('package_version_id', $version->id)->delete();
            $rows = [];
            foreach (array_values(array_unique(array_map('intval', $foodItemIds))) as $id) {
                $rows[] = ['package_version_id' => $version->id, 'dish_id' => $id, ...$this->foodDetails($version, $id, $details[$id] ?? []), 'created_at' => now(), 'updated_at' => now()];
            }
            DB::table('package_version_food_items')->insert($rows);
            DB::table('package_version_required_categories')->where('package_version_id', $version->id)->delete();
            foreach ($categoryRules as $rule) {
                DB::table('package_version_required_categories')->insert([
                    'package_version_id' => $version->id,
                    'category_id' => (int) $rule['category_id'],
                    'minimum_count' => (int) $rule['minimum_count'],
                    'maximum_count' => (int) $rule['maximum_count'],
                    'maximum_count_explicit' => (bool) ($rule['maximum_count_explicit'] ?? false),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            if ($promoteSingleRequiredItems) {
                $this->promoteSingleRequiredItems($version);
            }
        });
    }

    private function foodDetails(PackageVersion $version, int $dishId, array $data): array
    {
        $behavior = $version->family->behavior_type;
        $fixed = (bool) ($data['is_fixed'] ?? false);
        $quantity = $data['included_quantity'] ?? null;
        $unit = $data['included_unit'] ?? null;
        $unitId = $data['included_unit_id'] ?? null;
        $amount = app(MinorMoney::class)->fromPesos($data['price_adjustment_pesos'] ?? '0', 'food_details');
        if ($behavior === 'main_package' && ($quantity !== null || $unit !== null || $unitId !== null || $amount !== 0)) {
            throw ValidationException::withMessages(['food_details' => 'Catering foods have equal price standing and no per-food quantities.']);
        }
        if ($quantity === null && ($unit !== null || $unitId !== null)) {
            throw ValidationException::withMessages(['food_details' => 'Choose a portion amount and measurement unit together.']);
        }
        if ($quantity !== null && (! is_numeric($quantity) || $quantity <= 0)) {
            throw ValidationException::withMessages(['food_details' => 'A fixed portion needs a positive amount.']);
        }
        if ($quantity !== null && $unitId !== null) {
            if ((! is_int($unitId) && (! is_string($unitId) || ! ctype_digit($unitId))) || (int) $unitId < 1) {
                throw ValidationException::withMessages(['food_details' => 'Please select a valid measurement unit.']);
            }
            $selected = MeasurementUnit::whereKey($unitId)->where('active', true)->lockForUpdate()->first();
            if (! $selected) {
                throw ValidationException::withMessages(['food_details' => 'Please select an active measurement unit.']);
            }
            $unit = $selected->abbreviation;
        }
        if ($quantity !== null && $unitId === null) {
            // An old published or Draft configuration can retain its frozen text
            // while the Admin completes unrelated Draft changes. New UI input
            // always sends a selected active Measurement Unit id.
            $existing = DB::table('package_version_food_items')
                ->where('package_version_id', $version->id)->where('dish_id', $dishId)->first(['included_quantity', 'included_unit']);
            if (! is_string($unit) || ! $existing || (string) $existing->included_quantity !== (string) $quantity || $existing->included_unit !== $unit) {
                throw ValidationException::withMessages(['food_details' => 'Please select a valid measurement unit.']);
            }
        }
        if ($behavior === 'fixed_bundle' && $amount !== 0) {
            throw ValidationException::withMessages(['food_details' => 'Fixed bundles cannot have food substitutions or premiums.']);
        }

        return ['is_fixed' => $behavior === 'fixed_bundle' || $fixed, 'always_include' => $fixed, 'included_quantity' => $quantity,
            'included_unit' => $unit, 'price_adjustment_minor' => $amount];
    }

    private function validateIds(PackageVersion $version, array $foodItemIds, array $categoryRules, array $details): void
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
        $categoryIds = array_column($categoryRules, 'category_id');
        if (count(array_unique($categoryIds)) !== count($categoryRules)) {
            throw ValidationException::withMessages(['category_choice_rules' => 'A Food Category can only appear once in customer choices.']);
        }
        $categories = DB::table('dish_categories')->whereIn('id', $categoryIds)->where('active', true)->count();
        if ($categories !== count($categoryRules)) {
            throw ValidationException::withMessages(['category_choice_rules' => 'Customer Choice Categories must be active.']);
        }
        foreach ($categoryRules as $rule) {
            $minimum = (int) ($rule['minimum_count'] ?? -1);
            $maximum = (int) ($rule['maximum_count'] ?? 0);
            if ($minimum < 0 || $maximum < 1 || $minimum > $maximum) {
                throw ValidationException::withMessages(['category_choice_rules' => 'A Customer Choice minimum cannot exceed its maximum.']);
            }
            $choiceCount = Dish::whereIn('id', $ids)->where('category_id', $rule['category_id'])
                ->whereNotIn('id', collect($details)->filter(fn ($detail) => (bool) ($detail['is_fixed'] ?? false))->keys()->map(fn ($id) => (int) $id)->all())
                ->count();
            if (($rule['maximum_count_explicit'] ?? false) && $choiceCount < $maximum) {
                throw ValidationException::withMessages(['category_choice_rules' => "This Customer Choice Group needs {$maximum} eligible Food Item option(s), but only {$choiceCount} are available."]);
            }
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

    private function validateCategorySelections($rules, $available, $selected, array $selectedIds): void
    {
        foreach ($rules as $category) {
            $items = $available->where('category_id', $category->id);
            $choices = $items->reject(fn ($dish) => (bool) $dish->pivot->is_fixed);
            $legacyFixedOnly = $choices->isEmpty() && $items->count() === 1 && (bool) $items->first()->pivot->is_fixed;
            if ($legacyFixedOnly) {
                continue;
            }
            $chosen = $selected
                ->where('category_id', $category->id)
                ->reject(fn ($dish) => (bool) $dish->pivot->is_fixed)
                ->count();
            $minimum = (int) ($category->pivot->minimum_count ?? 1);
            $maximum = (int) ($category->pivot->maximum_count ?? 1);
            if ($chosen < $minimum) {
                throw ValidationException::withMessages(['food_items' => "Select at least {$minimum} Food Item(s) from {$category->name}."]);
            }
            if ($chosen > $maximum) {
                throw ValidationException::withMessages(['food_items' => "Select no more than {$maximum} Food Item(s) from {$category->name}."]);
            }
        }
    }

    /** @return list<array{id:int,name:string,minimum_count:int,maximum_count:int}> */
    private function snapshotCategoryRules(array $menu): array
    {
        if (isset($menu['category_choice_rules']) && is_array($menu['category_choice_rules'])) {
            return array_map(fn (array $rule): array => [
                'id' => (int) $rule['id'],
                'name' => (string) $rule['name'],
                'minimum_count' => (int) $rule['minimum_count'],
                'maximum_count' => (int) $rule['maximum_count'],
                'maximum_count_explicit' => (bool) ($rule['maximum_count_explicit'] ?? false),
            ], $menu['category_choice_rules']);
        }

        return array_map(fn (array $category): array => [
            'id' => (int) $category['id'],
            'name' => (string) $category['name'],
            'minimum_count' => 1,
            'maximum_count' => (int) ($menu['total_selection_count'] ?? 1),
            'maximum_count_explicit' => false,
        ], $menu['required_categories'] ?? []);
    }

    private function validateSnapshotCategorySelections(array $menu, $selected, array $selectedIds): void
    {
        $available = collect($menu['food_items']);
        foreach ($this->snapshotCategoryRules($menu) as $rule) {
            $items = $available->where('category_id', $rule['id']);
            $choices = $items->reject(fn (array $item) => (bool) $item['is_fixed']);
            $legacyFixedOnly = $choices->isEmpty() && $items->count() === 1 && (bool) $items->first()['is_fixed'];
            if ($legacyFixedOnly) {
                continue;
            }
            $chosen = $selected
                ->where('category_id', $rule['id'])
                ->reject(fn (array $item) => (bool) $item['is_fixed'])
                ->count();
            if ($chosen < $rule['minimum_count']) {
                throw ValidationException::withMessages(['food_items' => "Select at least {$rule['minimum_count']} Food Item(s) from {$rule['name']}."]);
            }
            if ($chosen > $rule['maximum_count']) {
                throw ValidationException::withMessages(['food_items' => "Select no more than {$rule['maximum_count']} Food Item(s) from {$rule['name']}."]);
            }
        }
    }
}
