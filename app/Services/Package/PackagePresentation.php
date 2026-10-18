<?php

namespace App\Services\Package;

use App\Models\MeasurementUnit;
use App\Models\PackageVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Physical presentation never changes food measurements, selection counts or prices. */
class PackagePresentation
{
    public function input(PackageVersion $version): ?array
    {
        if ($version->package_format === null) {
            return null;
        }

        return ['format' => $version->package_format,
            'quantity' => $version->presentation_quantity, 'measurement_unit_id' => $version->presentation_unit_id,
            'food_overrides' => $version->foodItems()->get()->filter(fn ($food) => $food->pivot->getAttribute('presentation_unit_id') !== null)
                ->mapWithKeys(fn ($food) => [$food->id => ['quantity' => $food->pivot->getAttribute('presentation_quantity'), 'measurement_unit_id' => $food->pivot->getAttribute('presentation_unit_id')]])->all()];
    }

    public function validate(PackageVersion $version, ?array $input, array $foodIds): void
    {
        if ($input === null) {
            return;
        }
        if (in_array($version->family->behavior_type, ['main_package', 'direct_sale'], true)) {
            throw ValidationException::withMessages(['presentation' => 'Package Format is available only for add-on packages.']);
        }
        if ($version->selection_policy !== 'uniform') {
            throw ValidationException::withMessages(['presentation' => 'Package Format uses one complete food combination repeated across purchased units. Keep legacy split configuration unchanged, or explicitly choose uniform selections first.']);
        }
        Validator::make(['presentation' => $input], [
            'presentation' => ['array:format,quantity,measurement_unit_id,food_overrides'],
            'presentation.format' => ['required', Rule::in(['separate_servings', 'combined_contents'])],
            'presentation.quantity' => ['nullable', 'required_with:presentation.measurement_unit_id', 'integer', 'between:1,2147483647'],
            'presentation.measurement_unit_id' => ['nullable', 'required_with:presentation.quantity', 'integer'],
            'presentation.food_overrides' => ['sometimes', 'array'],
            'presentation.food_overrides.*' => ['array:quantity,measurement_unit_id'],
            'presentation.food_overrides.*.quantity' => ['required', 'integer', 'between:1,2147483647'],
            'presentation.food_overrides.*.measurement_unit_id' => ['required', 'integer'],
        ])->validate();
        $overrides = $input['food_overrides'] ?? [];
        foreach ([$input, ...array_values($overrides)] as $amount) {
            if (is_bool($amount['quantity'] ?? null) || is_bool($amount['measurement_unit_id'] ?? null)) {
                throw ValidationException::withMessages(['presentation' => 'Use whole container counts and valid presentation units, not boolean values.']);
            }
        }
        if ($input['format'] === 'combined_contents' && ((int) ($input['quantity'] ?? 0) !== 1 || empty($input['measurement_unit_id']) || $overrides !== [])) {
            throw ValidationException::withMessages(['presentation' => 'Choose one complete container for Combined Contents. Separate food-container overrides do not apply; remove them explicitly before changing format.']);
        }
        if (array_diff(array_map('strval', array_keys($overrides)), array_map('strval', $foodIds)) !== []) {
            throw ValidationException::withMessages(['presentation.food_overrides' => 'Presentation overrides must belong to foods in this package.']);
        }
        $ids = array_values(array_unique(array_filter([$input['measurement_unit_id'] ?? null, ...array_column($overrides, 'measurement_unit_id')])));
        $activeIds = MeasurementUnit::whereIn('id', $ids)->where('active', true)->lockForUpdate()->pluck('id')->all();
        if (count($activeIds) !== count($ids)) {
            throw ValidationException::withMessages(['presentation' => 'Choose active Measurement Units for presentation.']);
        }
    }

    /** Called inside the existing atomic menu save, after menu replacement. */
    public function save(PackageVersion $version, ?array $input): void
    {
        $version->forceFill(['package_format' => $input['format'] ?? null,
            'presentation_quantity' => $input['quantity'] ?? null,
            'presentation_unit_id' => $input['measurement_unit_id'] ?? null])->save();
        DB::table('package_version_food_items')->where('package_version_id', $version->id)->update(['presentation_quantity' => null, 'presentation_unit_id' => null]);
        foreach ($input['food_overrides'] ?? [] as $foodId => $override) {
            DB::table('package_version_food_items')->where('package_version_id', $version->id)->where('dish_id', $foodId)
                ->update(['presentation_quantity' => $override['quantity'], 'presentation_unit_id' => $override['measurement_unit_id']]);
        }
    }

    public function snapshot(PackageVersion $version): ?array
    {
        $input = $this->input($version);
        if ($input === null) {
            return null;
        }
        $units = MeasurementUnit::whereIn('id', array_filter([$input['measurement_unit_id'], ...array_column($input['food_overrides'], 'measurement_unit_id')]))->get()->keyBy('id');
        $freeze = fn (array $value): array => ['quantity' => (int) $value['quantity'], 'measurement_unit' => $units[$value['measurement_unit_id']]->only('id', 'name', 'abbreviation')];

        return ['format' => $input['format'], 'default' => $input['measurement_unit_id'] === null ? null : $freeze($input),
            'food_overrides' => array_map($freeze, $input['food_overrides'])];
    }

    public function effective(PackageVersion $version): ?array
    {
        return $version->status === 'published' ? ($version->snapshot()['presentation'] ?? null) : $this->snapshot($version);
    }

    public function purchaseUnit(PackageVersion $version): ?string
    {
        $presentation = $this->effective($version);
        if ($presentation === null) {
            return null;
        }

        return $presentation['format'] === 'combined_contents'
            ? 'complete '.$presentation['default']['measurement_unit']['name']
            : 'complete package set';
    }
}
