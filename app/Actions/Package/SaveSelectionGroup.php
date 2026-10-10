<?php

namespace App\Actions\Package;

use App\Enums\Catalog\CollectionBehavior;
use App\Models\OfferingOption;
use App\Models\SelectionGroup;
use App\Models\User;
use App\Models\VariantVersion;
use App\Services\Audit\AuditLogger;
use App\Services\Catalog\CatalogAvailability;
use App\Services\Catalog\MinorMoney;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaveSelectionGroup
{
    public function __construct(private AuditLogger $audit, private CatalogAvailability $availability, private MinorMoney $money) {}

    public function handle(User $actor, VariantVersion $version, array $data, ?SelectionGroup $group = null): SelectionGroup
    {
        return DB::transaction(function () use ($actor, $version, $data, $group) {
            $version = VariantVersion::with('family')->lockForUpdate()->findOrFail($version->id);
            abort_if($version->status !== 'draft', 404);
            $group = $group ? SelectionGroup::lockForUpdate()->findOrFail($group->id) : new SelectionGroup;
            abort_if($group->exists && $group->version_id !== $version->id, 404);
            Gate::forUser($actor->fresh())->authorize($group->exists ? 'update' : 'create', $group->exists ? $group : SelectionGroup::class);
            $this->validateShape($version, $data);
            $options = [];
            $dishIds = [];
            foreach ($data['options'] as $row) {
                $option = OfferingOption::with('offering')->findOrFail($row['offering_option_id']);
                if ($option->offering->family_id !== $version->family_id || ! $this->availability->optionAvailable($option)) {
                    throw ValidationException::withMessages(['options' => 'Only currently eligible serving options from this collection may be used.']);
                }
                if (
                    $data['slot_kind'] === 'lechon' && $option->unit !== 'kg'
                    || $data['slot_kind'] === 'tray' && $option->unit !== 'tray'
                ) {
                    throw ValidationException::withMessages(['options' => 'The serving unit does not match this selection group type.']);
                }
                $amount = $this->money->parse($row['price_adjustment_minor'], 'options');
                if ($version->family->behavior() === CollectionBehavior::FixedBundle && $amount !== 0) {
                    throw ValidationException::withMessages(['options' => "Fixed inclusions in {$version->family->name} cannot carry premiums."]);
                }
                $options[$option->id] = [
                    'offering_option_id' => $option->id,
                    'is_default' => $row['is_default'],
                    'price_adjustment_minor' => $amount,
                ];
                $dishIds[] = $option->offering->dish_id;
            }
            if (count($options) !== count($data['options'])) {
                throw ValidationException::withMessages(['options' => 'Do not repeat a serving option in the same selection group.']);
            }
            if ($data['maximum_count'] > count($options)) {
                throw ValidationException::withMessages(['options' => "{$data['name']} allows at most ".count($options).' currently configured options. Reduce its maximum selection count or add options.']);
            }
            if ($data['unique_dishes'] && $data['maximum_count'] > count(array_unique($dishIds))) {
                throw ValidationException::withMessages(['options' => "{$data['name']} needs {$data['maximum_count']} distinct permitted dishes."]);
            }
            $defaultCount = count(array_filter($options, fn ($row) => $row['is_default']));
            if (($data['mode'] === 'fixed' && (count($options) !== 1 || $defaultCount !== 1))
                || ($data['mode'] === 'select_one' && $data['minimum_count'] === 1 && $defaultCount !== 1)
                || $defaultCount > $data['maximum_count']
            ) {
                throw ValidationException::withMessages(['options' => "Defaults and option count for {$data['name']} do not match its {$data['mode']} rule (select {$data['minimum_count']} to {$data['maximum_count']})."]);
            }
            $before = $group->exists ? $group->only(
                'group_code',
                'name',
                'mode',
                'slot_kind',
                'minimum_count',
                'maximum_count',
                'unique_dishes',
                'category_mode',
                'inclusion_count',
                'sort_order'
            ) : null;
            if ($before !== null) {
                $before['options'] = DB::table('group_options')->where('group_id', $group->id)
                    ->get(['offering_option_id', 'is_default', 'price_adjustment_minor'])->map(fn ($row) => (array) $row)->all();
                $before['category_rules'] = DB::table('group_category_rules')->where('group_id', $group->id)
                    ->get(['category_id', 'minimum_count', 'maximum_count'])->map(fn ($row) => (array) $row)->all();
                $before['dish_rules'] = DB::table('group_dish_rules')->where('group_id', $group->id)
                    ->get(['dish_id', 'effect'])->map(fn ($row) => (array) $row)->all();
            }
            $oldPrices = $group->exists ? DB::table('group_options')->where('group_id', $group->id)
                ->pluck('price_adjustment_minor', 'offering_option_id')->all() : [];
            $newPrices = array_map(fn ($row) => $row['price_adjustment_minor'], $options);
            if (
                array_filter($oldPrices, fn ($amount) => (int) $amount !== 0)
                != array_filter($newPrices, fn ($amount) => $amount !== 0)
            ) {
                Gate::forUser($actor->fresh())->authorize('price', VariantVersion::class);
            }
            $group->fill(collect($data)->except('options', 'category_rules', 'dish_rules')->all());
            if (! $group->exists) {
                $group->version_id = $version->id;
            }
            $group->save();
            DB::table('group_options')->where('group_id', $group->id)->delete();
            foreach ($options as $row) {
                DB::table('group_options')->insert([
                    ...$row,
                    'group_id' => $group->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            DB::table('group_category_rules')->where('group_id', $group->id)->delete();
            foreach ($data['category_rules'] as $row) {
                DB::table('group_category_rules')->insert([
                    ...$row,
                    'group_id' => $group->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            DB::table('group_dish_rules')->where('group_id', $group->id)->delete();
            foreach ($data['dish_rules'] as $row) {
                DB::table('group_dish_rules')->insert([
                    ...$row,
                    'group_id' => $group->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $this->audit->record(
                $before ? 'package.group_updated' : 'package.group_created',
                $actor,
                $group,
                $before,
                [
                    ...$group->only(
                        'group_code',
                        'name',
                        'mode',
                        'slot_kind',
                        'minimum_count',
                        'maximum_count',
                        'unique_dishes',
                        'category_mode',
                        'inclusion_count',
                        'sort_order'
                    ),
                    'options' => $options,
                    'category_rules' => $data['category_rules'],
                    'dish_rules' => $data['dish_rules'],
                ]
            );

            return $group;
        });
    }

    private function validateShape(VariantVersion $version, array $data): void
    {
        $fixedBundle = $version->family->behavior() === CollectionBehavior::FixedBundle;
        $allowed = $fixedBundle ? ['item', 'bundle_item']
            : ['item', 'rice', 'drink', 'dish', 'lechon', 'tray', 'bundle_item'];
        if (
            ! in_array($data['slot_kind'], $allowed, true)
            || ($fixedBundle && $data['mode'] !== 'fixed')
            || ($data['slot_kind'] === 'rice' && $data['mode'] !== 'fixed')
            || ($data['slot_kind'] === 'drink' && $data['mode'] !== 'select_one')
            || ($data['slot_kind'] === 'dish' && $data['mode'] !== 'select_many')
            || ($data['slot_kind'] === 'lechon' && ! in_array($data['mode'], ['fixed', 'select_one'], true))
            || ($data['slot_kind'] === 'tray' && $data['mode'] !== 'select_many')
            || ($data['slot_kind'] === 'bundle_item' && $data['mode'] !== 'fixed')
            || ($data['mode'] === 'fixed' && ($data['minimum_count'] !== 1 || $data['maximum_count'] !== 1))
            || ($data['mode'] === 'select_one' && $data['maximum_count'] !== 1)
            || $data['minimum_count'] > $data['maximum_count']
        ) {
            throw ValidationException::withMessages(['mode' => "The {$data['slot_kind']} selection group configuration conflicts with {$version->family->name} rules."]);
        }
        if (
            $data['category_mode'] === 'free' && $data['category_rules'] !== []
            || $data['category_mode'] === 'restricted' && $data['category_rules'] === []
        ) {
            throw ValidationException::withMessages(['category_rules' => 'Restricted selection groups need category rules; free selection groups must not have them.']);
        }
        $minimumSum = 0;
        foreach ($data['category_rules'] as $rule) {
            if ($rule['minimum_count'] > $rule['maximum_count']) {
                throw ValidationException::withMessages(['category_rules' => 'A category minimum exceeds its maximum.']);
            }
            $minimumSum += $rule['minimum_count'];
        }
        if ($minimumSum > $data['maximum_count']) {
            throw ValidationException::withMessages(['category_rules' => 'Category minima exceed the selection group maximum.']);
        }
        if (
            count(array_unique(array_column($data['category_rules'], 'category_id'))) !== count($data['category_rules'])
            || count(array_unique(array_column($data['dish_rules'], 'dish_id'))) !== count($data['dish_rules'])
        ) {
            throw ValidationException::withMessages(['rules' => 'Each food item or category may have only one rule per selection group.']);
        }
    }
}
