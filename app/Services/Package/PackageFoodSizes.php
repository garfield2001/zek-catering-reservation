<?php

namespace App\Services\Package;

use App\Models\MeasurementUnit;
use App\Models\PackageVersion;
use App\Services\Catalog\MinorMoney;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PackageFoodSizes
{
    public function __construct(private MinorMoney $money) {}

    public function validateInput(PackageVersion $version, array $foodIds, array $groups): array
    {
        if ($groups && $version->family->behavior_type === 'main_package') {
            throw ValidationException::withMessages(['food_sizes' => 'Catering Packages do not have per-food size upgrades.']);
        }
        Validator::make(['food_sizes' => $groups], [
            'food_sizes' => ['array'], 'food_sizes.*' => ['array:measurement_unit_id,choices'],
            'food_sizes.*.measurement_unit_id' => ['required', 'integer'],
            'food_sizes.*.choices' => ['required', 'array', 'min:1', 'max:100'],
            'food_sizes.*.choices.*' => ['array:quantity,size_label,extra_price_pesos,is_default'],
            'food_sizes.*.choices.*.size_label' => ['nullable', 'string', 'max:100'],
            'food_sizes.*.choices.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999.999', 'decimal:0,3'],
            'food_sizes.*.choices.*.extra_price_pesos' => ['required', 'string', 'max:20'],
            'food_sizes.*.choices.*.is_default' => ['required', 'boolean'],
        ])->validate();
        $result = [];
        foreach ($groups as $foodId => $data) {
            if (! ctype_digit((string) $foodId) || ! in_array((int) $foodId, array_map('intval', $foodIds), true)) {
                throw ValidationException::withMessages(['food_sizes' => 'Size choices must belong to an allowed Food Item.']);
            }
            $unit = MeasurementUnit::whereKey($data['measurement_unit_id'])->where('active', true)->lockForUpdate()->first();
            if (! $unit) {
                throw ValidationException::withMessages(["food_sizes.$foodId.measurement_unit_id" => 'Choose an active Measurement Unit.']);
            }
            $choices = [];
            $defaults = 0;
            foreach ($data['choices'] as $index => $choice) {
                $key = "food_sizes.$foodId.choices.$index";
                // NUMERIC(12,3) is quantity, never money; canonicalize duplicates as PostgreSQL does.
                $quantity = number_format((float) $choice['quantity'], 3, '.', '');
                $amount = $this->money->fromPesos($choice['extra_price_pesos'], "$key.extra_price_pesos");
                $label = trim($choice['size_label'] ?? '');
                $label = $label === '' ? null : $label;
                $identity = $label !== null ? 'named:'.mb_strtolower($label) : 'numeric:'.$quantity;
                if (isset($choices[$identity])) {
                    throw ValidationException::withMessages(["$key.quantity" => 'Each size must be distinct within this food.']);
                }
                $default = (bool) $choice['is_default'];
                $defaults += (int) $default;
                if ($default && $amount !== 0) {
                    throw ValidationException::withMessages(["$key.extra_price_pesos" => 'The default included size must have zero extra price.']);
                }
                $choices[$identity] = ['quantity' => $quantity, 'size_label' => $label, 'extra_price_minor' => (string) $amount, 'is_default' => $default];
            }
            if ($defaults !== 1) {
                throw ValidationException::withMessages(["food_sizes.$foodId.choices" => 'Choose exactly one default included size.']);
            }
            $result[(int) $foodId] = ['measurement_unit_id' => $unit->id, 'choices' => array_values($choices)];
        }

        return $result;
    }

    public function clear(PackageVersion $version): void
    {
        $ids = $version->sizeGroups()->pluck('id');
        DB::table('package_food_size_choices')->whereIn('size_group_id', $ids)->delete();
        $version->sizeGroups()->delete();
    }

    /** Compare persisted facts, not input formatting or generated child identifiers. */
    public function configuration(PackageVersion $version): array
    {
        return $version->sizeGroups()->with('choices')->get()->mapWithKeys(fn ($group) => [$group->dish_id => [
            'measurement_unit_id' => $group->measurement_unit_id,
            'choices' => $group->choices->map(fn ($choice) => [
                'quantity' => $choice->quantity, 'size_label' => $choice->size_label, 'extra_price_minor' => (string) $choice->extra_price_minor,
                'is_default' => $choice->is_default,
            ])->all(),
        ]])->all();
    }

    public function replace(PackageVersion $version, array $groups): void
    {
        foreach ($groups as $foodId => $data) {
            $group = $version->sizeGroups()->create(['dish_id' => $foodId, 'measurement_unit_id' => $data['measurement_unit_id']]);
            $group->choices()->createMany($data['choices']);
        }
        $version->unsetRelation('sizeGroups');
    }

    public function snapshot(PackageVersion $version): array
    {
        return $version->sizeGroups()->with(['unit', 'choices'])->get()->mapWithKeys(fn ($group) => [$group->dish_id => [
            'measurement_unit' => $group->unit->only('id', 'name', 'abbreviation'),
            'choices' => $group->choices->map(fn ($choice) => ['id' => $choice->id, 'quantity' => $choice->quantity, 'size_label' => $choice->size_label,
                'extra_price_minor' => (string) $choice->extra_price_minor, 'is_default' => $choice->is_default])->all(),
        ]])->all();
    }

    public function input(PackageVersion $version): array
    {
        return $version->sizeGroups()->with('choices')->get()->mapWithKeys(fn ($group) => [$group->dish_id => [
            'measurement_unit_id' => $group->measurement_unit_id,
            'choices' => $group->choices->map(fn ($choice) => ['quantity' => $choice->quantity, 'size_label' => $choice->size_label,
                'extra_price_pesos' => intdiv((int) $choice->extra_price_minor, 100).'.'.str_pad((string) ((int) $choice->extra_price_minor % 100), 2, '0', STR_PAD_LEFT),
                'is_default' => $choice->is_default])->all(),
        ]])->all();
    }

    public function validateDraft(PackageVersion $version): void
    {
        foreach ($version->sizeGroups()->with(['unit', 'choices'])->get() as $group) {
            if (! $group->unit->active || $group->choices->where('is_default', true)->count() !== 1) {
                throw ValidationException::withMessages(['food_sizes' => 'Each food size group needs an active Measurement Unit and one included default before publishing.']);
            }
        }
    }

    /** Resolve one mutually exclusive choice per selected food; omitted choices use the included default. */
    public function resolve(PackageVersion $version, array $foodIds, array $selections): array
    {
        $groups = $version->status === 'published' ? ($version->snapshot()['menu']['food_sizes'] ?? []) : $this->snapshot($version);
        foreach ($selections as $foodId => $choiceId) {
            if (! in_array((int) $foodId, $foodIds, true) || ! isset($groups[$foodId])
                || (! is_int($choiceId) && ! is_string($choiceId)) || ! ctype_digit((string) $choiceId)) {
                throw ValidationException::withMessages(['food_sizes' => 'Choose valid sizes only for selected foods.']);
            }
        }
        $result = [];
        foreach ($groups as $foodId => $group) {
            if (! in_array((int) $foodId, $foodIds, true)) {
                continue;
            }
            $choices = collect($group['choices']);
            $choice = isset($selections[$foodId]) ? $choices->firstWhere('id', (int) $selections[$foodId]) : $choices->firstWhere('is_default', true);
            if (! $choice) {
                throw ValidationException::withMessages(['food_sizes' => 'The selected size does not belong to this food and package version.']);
            }
            $result[$foodId] = [...$choice, 'measurement_unit' => $group['measurement_unit']];
        }
        ksort($result);

        return $result;
    }

    public function commercialPrices(array $groups): array
    {
        $result = [];
        foreach ($groups as $foodId => $group) {
            foreach ($group['choices'] as $choice) {
                $result[$foodId.':'.$group['measurement_unit']['id'].':'.(($choice['size_label'] ?? null) !== null ? 'named:'.mb_strtolower($choice['size_label']) : $choice['quantity'])] = (string) $choice['extra_price_minor'];
            }
        }
        ksort($result);

        return $result;
    }
}
