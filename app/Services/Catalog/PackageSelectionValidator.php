<?php

namespace App\Services\Catalog;

use App\Enums\Catalog\CollectionBehavior;
use App\Models\GroupOption;
use App\Models\PackageVersion;
use App\Models\SelectionGroup;
use Illuminate\Validation\ValidationException;

class PackageSelectionValidator
{
    public function __construct(private CatalogAvailability $availability) {}

    public function validate(PackageVersion $version, int $totalQuantity, array $allocations): array
    {
        $version->loadMissing(['family', 'groups.options.offeringOption.offering.dish', 'groups.categoryRules.category', 'groups.dishRules']);
        if ($version->groups->isEmpty()) {
            throw ValidationException::withMessages(['groups' => "Add inclusion groups to {$version->family->name} before previewing."]);
        }
        if ($version->selection_policy === 'uniform' && count($allocations) !== 1) {
            throw ValidationException::withMessages(['groups' => "{$version->family->name} requires one uniform allocation for this package."]);
        }
        if ($version->selection_policy !== 'split' && $version->selection_policy !== 'uniform') {
            throw ValidationException::withMessages(['groups' => 'Unsupported selection policy.']);
        }
        if (! $version->behavior()->supportsSplit() && $version->selection_policy === 'split') {
            throw ValidationException::withMessages(['groups' => "Split allocations are unavailable for {$version->family->name}; use one uniform allocation."]);
        }
        if ($allocations === []) {
            throw ValidationException::withMessages(['groups' => 'At least one selection group is required.']);
        }

        $resolved = [];
        $quantitySum = 0;
        foreach ($allocations as $index => $allocation) {
            $quantity = $allocation['quantity'] ?? null;
            if (! is_int($quantity) || $quantity < 1 || $quantity > PHP_INT_MAX - $quantitySum) {
                throw ValidationException::withMessages(["groups.$index.quantity" => 'Group quantity must be a positive integer.']);
            }
            $quantitySum += $quantity;
            $selections = $allocation['selections'] ?? null;
            if (! is_array($selections)) {
                throw ValidationException::withMessages(["groups.$index.selections" => 'Selections must be grouped by configured slot.']);
            }
            $unknown = array_diff(array_keys($selections), $version->groups->pluck('group_code')->all());
            if ($unknown !== []) {
                throw ValidationException::withMessages(["groups.$index.selections" => 'An unknown selection group was submitted.']);
            }
            $chosen = [];
            $customDishIds = [];
            foreach ($version->groups as $group) {
                $chosen[$group->group_code] = $this->validateGroup($version, $group,
                    $selections[$group->group_code] ?? null, "groups.$index.selections.{$group->group_code}");
                if ($group->mode !== 'fixed') {
                    foreach ($chosen[$group->group_code] as $choice) {
                        $dishId = $choice->offeringOption->offering->dish_id;
                        if (in_array($dishId, $customDishIds, true)) {
                            throw ValidationException::withMessages(["groups.$index.selections" => 'Choose distinct dishes across customizable slots.']);
                        }
                        $customDishIds[] = $dishId;
                    }
                }
            }
            $resolved[] = ['quantity' => $quantity, 'selections' => $chosen];
        }
        if ($quantitySum !== $totalQuantity) {
            throw ValidationException::withMessages(['groups' => "Allocation quantities for {$version->family->name} must total {$totalQuantity}."]);
        }
        if ($version->selection_policy === 'split') {
            $resolved = $this->mergeIdentical($resolved);
            foreach ($resolved as $index => $allocation) {
                app(PackageQuantityRules::class)->validateSubgroup($version, $allocation['quantity'], "groups.$index.quantity");
            }
        }

        return $resolved;
    }

    private function validateGroup(PackageVersion $version, SelectionGroup $group, mixed $submitted, string $field): array
    {
        if ($version->behavior() === CollectionBehavior::FixedBundle && $group->mode !== 'fixed') {
            throw ValidationException::withMessages([$field => "Inclusions in {$version->family->name} must be fixed."]);
        }
        if ($group->mode === 'fixed') {
            if ($group->options->count() !== 1) {
                throw ValidationException::withMessages([$field => "Fixed selection group {$group->name} must have exactly one configured serving option."]);
            }
            $ids = [$group->options->first()->offering_option_id];
            if ($submitted !== null && $submitted !== [] && $submitted !== $ids) {
                throw ValidationException::withMessages([$field => "Fixed selection group {$group->name} cannot be changed."]);
            }
        } else {
            $ids = $submitted ?? $group->options->where('is_default', true)->pluck('offering_option_id')->all();
            if (! is_array($ids) || ! array_is_list($ids) || count(array_filter($ids, 'is_int')) !== count($ids)) {
                throw ValidationException::withMessages([$field => 'Select valid option identifiers.']);
            }
        }
        if (count($ids) < $group->minimum_count || count($ids) > $group->maximum_count) {
            $required = $group->minimum_count === $group->maximum_count
                ? "exactly {$group->minimum_count}" : "between {$group->minimum_count} and {$group->maximum_count}";
            throw ValidationException::withMessages([$field => "Choose {$required} option(s) for {$group->name}."]);
        }
        $allowed = $group->options->keyBy('offering_option_id');
        $selected = [];
        $dishIds = [];
        $categoryCounts = [];
        $allowRules = $group->dishRules->where('effect', 'allow')->pluck('dish_id')->all();
        $denyRules = $group->dishRules->where('effect', 'deny')->pluck('dish_id')->all();
        $categoryRules = $group->categoryRules->keyBy('category_id');
        foreach ($ids as $id) {
            /** @var GroupOption|null $choice */
            $choice = $allowed->get($id);
            if (! $choice || ! $choice->offeringOption || ! $this->availability->optionAvailable($choice->offeringOption)) {
                throw ValidationException::withMessages([$field => "A choice for {$group->name} is not permitted or currently available."]);
            }
            $dish = $choice->offeringOption->offering->dish;
            if ($choice->offeringOption->offering->family_id !== $version->family_id
                || in_array($dish->id, $denyRules, true)
                || ($allowRules !== [] && ! in_array($dish->id, $allowRules, true))
                || ($group->category_mode === 'restricted' && ! $categoryRules->has($dish->category_id))) {
                throw ValidationException::withMessages([$field => "A selected food item is excluded by {$group->name} rules."]);
            }
            $dishIds[] = $dish->id;
            $categoryCounts[$dish->category_id] = ($categoryCounts[$dish->category_id] ?? 0) + 1;
            $selected[] = $choice;
        }
        if ($group->unique_dishes && count(array_unique($dishIds)) !== count($dishIds)) {
            throw ValidationException::withMessages([$field => "Choose distinct dishes for {$group->name}."]);
        }
        if ($group->category_mode === 'restricted') {
            if ($categoryRules->isEmpty()) {
                throw ValidationException::withMessages([$field => "Restricted selection group {$group->name} has no category rules."]);
            }
            foreach ($categoryRules as $categoryId => $rule) {
                $count = $categoryCounts[$categoryId] ?? 0;
                if ($count < $rule->minimum_count || $count > $rule->maximum_count) {
                    throw ValidationException::withMessages([$field => "Selected dishes for {$group->name} must include {$rule->minimum_count} to {$rule->maximum_count} from {$rule->category->name}."]);
                }
            }
        }

        return $selected;
    }

    private function mergeIdentical(array $allocations): array
    {
        $merged = [];
        foreach ($allocations as $allocation) {
            $signature = [];
            foreach ($allocation['selections'] as $code => $options) {
                $ids = array_map(fn (GroupOption $option) => $option->offering_option_id, $options);
                sort($ids);
                $signature[$code] = $ids;
            }
            $key = json_encode($signature, JSON_THROW_ON_ERROR);
            if (isset($merged[$key])) {
                $merged[$key]['quantity'] += $allocation['quantity'];
            } else {
                $merged[$key] = $allocation;
            }
        }

        return array_values($merged);
    }
}
