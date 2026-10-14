<?php

namespace App\Services\Package;

use App\Models\PackageVersion;
use App\Models\SelectionGroup;
use App\Services\Catalog\CatalogAvailability;
use Illuminate\Validation\ValidationException;

class ConfigurationFeasibility
{
    private const MAX_STATES = 100000;

    private int $visited = 0;

    public function __construct(private CatalogAvailability $availability) {}

    /** @return array<int, array<int, int>> One valid selection of serving-option IDs per group. */
    public function find(PackageVersion $version): array
    {
        $version->load(['groups.options.offeringOption.offering.dish', 'groups.categoryRules', 'groups.dishRules']);
        if ($version->groups->isEmpty()) {
            throw ValidationException::withMessages(['groups' => 'Add at least one Included Item or Customer Choice before publishing.']);
        }
        $availableIds = $this->availability->availableOptionIds($version->groups->flatMap(
            fn ($group) => $group->options->pluck('offering_option_id')
        )->unique()->values()->all());
        $choices = [];
        foreach ($version->groups as $group) {
            $choices[] = [$group, $this->candidates($group, $version->family_id, $availableIds, $version->status === 'published')];
        }
        $this->visited = 0;
        $solution = $this->search($choices, 0, [], []);
        if ($solution === null) {
            throw ValidationException::withMessages(['groups' => 'The combined Customer Choices cannot be satisfied. Check shared Food Items, selection limits and Food Category Requirements.']);
        }

        return $solution;
    }

    /** @return array<int, array{option_id: int, dish_id: int, category_id: int}> */
    private function candidates(SelectionGroup $group, int $familyId, array $availableIds, bool $published): array
    {
        $allowed = [];
        $allowIds = $group->dishRules->where('effect', 'allow')->pluck('dish_id')->all();
        $denyIds = $group->dishRules->where('effect', 'deny')->pluck('dish_id')->all();
        $categories = $group->categoryRules->pluck('category_id')->all();
        foreach ($group->options as $choice) {
            $option = $choice->offeringOption;
            $dish = $option?->offering?->dish;
            $categoryId = $published ? $choice->snapshot_category_id : $dish?->category_id;
            $unit = $published ? $choice->snapshot_unit : $option?->unit;
            if (! $option || ! $dish || $option->offering->family_id !== $familyId
                || ($group->slot_kind === 'lechon' && $unit !== 'kg')
                || ($group->slot_kind === 'tray' && $unit !== 'tray')
                || ! isset($availableIds[$option->id])
                || in_array($dish->id, $denyIds, true)
                || ($allowIds !== [] && ! in_array($dish->id, $allowIds, true))
                || ($group->category_mode === 'restricted' && ! in_array($categoryId, $categories, true))) {
                continue;
            }
            $allowed[] = ['option_id' => $option->id, 'dish_id' => $dish->id, 'category_id' => $categoryId];
        }
        if ($group->mode === 'fixed' && (count($allowed) !== 1 || $group->options->count() !== 1
            || ! $group->options->first()->is_default)) {
            throw ValidationException::withMessages(['groups' => "Included Item {$group->name} needs its one configured, currently available Serving Option."]);
        }
        if (count($allowed) < $group->minimum_count) {
            throw ValidationException::withMessages(['groups' => "{$group->name} needs at least {$group->minimum_count} currently available choices; only ".count($allowed).' remain.']);
        }

        return $allowed;
    }

    /** @param array<int, array{SelectionGroup, array}> $groups
     * @param  array<int, bool>  $usedDishIds
     * @param  array<int, array<int, int>>  $chosen
     * @return array<int, array<int, int>>|null
     */
    private function search(array $groups, int $index, array $usedDishIds, array $chosen): ?array
    {
        if (++$this->visited > self::MAX_STATES) {
            throw ValidationException::withMessages(['groups' => 'The configuration exceeds the supported feasibility search limit. Reduce overlapping choices or split the rules into simpler groups.']);
        }
        if ($index === count($groups)) {
            return $chosen;
        }
        [$group, $options] = $groups[$index];
        $selected = [];
        $visit = function (int $start, array $localDishIds) use (&$visit, $groups, $index, $usedDishIds, $chosen, $group, $options): ?array {
            if (++$this->visited > self::MAX_STATES) {
                throw ValidationException::withMessages(['groups' => 'The configuration exceeds the supported feasibility search limit. Reduce overlapping choices or split the rules into simpler groups.']);
            }
            $count = count($localDishIds);
            if ($count >= $group->minimum_count && $this->categoryCountsValid($group, $localDishIds)) {
                $selectedIds = array_column($localDishIds, 'option_id');
                $nextUsed = $usedDishIds;
                if ($group->mode !== 'fixed') {
                    foreach ($localDishIds as $row) {
                        $nextUsed[$row['dish_id']] = true;
                    }
                }
                $nextChosen = $chosen;
                $nextChosen[$group->id] = $selectedIds;
                $result = $this->search($groups, $index + 1, $nextUsed, $nextChosen);
                if ($result !== null) {
                    return $result;
                }
            }
            if ($count >= $group->maximum_count) {
                return null;
            }
            for ($i = $start; $i < count($options); $i++) {
                $row = $options[$i];
                if ($group->mode !== 'fixed' && isset($usedDishIds[$row['dish_id']])) {
                    continue;
                }
                if ($group->unique_dishes && in_array($row['dish_id'], array_column($localDishIds, 'dish_id'), true)) {
                    continue;
                }
                $result = $visit($i + 1, [...$localDishIds, $row]);
                if ($result !== null) {
                    return $result;
                }
            }

            return null;
        };

        return $visit(0, $selected);
    }

    private function categoryCountsValid(SelectionGroup $group, array $selected): bool
    {
        if ($group->category_mode !== 'restricted') {
            return true;
        }
        if ($group->categoryRules->isEmpty()) {
            return false;
        }
        $counts = array_count_values(array_column($selected, 'category_id'));
        foreach ($group->categoryRules as $rule) {
            $count = $counts[$rule->category_id] ?? 0;
            if ($count < $rule->minimum_count || $count > $rule->maximum_count) {
                return false;
            }
        }

        return true;
    }
}
