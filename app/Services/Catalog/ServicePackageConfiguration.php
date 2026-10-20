<?php

namespace App\Services\Catalog;

use App\Models\MeasurementUnit;
use App\Models\ProductFamily;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Service defaults are future configuration, not a live override of package-version facts. */
class ServicePackageConfiguration
{
    public function input(ProductFamily $family): ?array
    {
        return $family->package_format === null ? null : [
            'format' => $family->package_format,
            'quantity' => $family->default_presentation_quantity,
            'measurement_unit_id' => $family->default_presentation_unit_id,
        ];
    }

    /** The caller holds the service row lock in its audited transaction. */
    public function normalize(ProductFamily $family, array $input): array
    {
        if (! $family->is_addon || ! in_array($family->behavior_type, ['package', 'packed_meal', 'fixed_bundle'], true)) {
            throw ValidationException::withMessages(['package_configuration' => 'Only services sold by package use a food format and default container.']);
        }
        Validator::make(['package_configuration' => $input], [
            'package_configuration' => ['required', 'array:format,quantity,measurement_unit_id'],
            'package_configuration.format' => ['required', Rule::in(['separate_servings', 'combined_contents'])],
            'package_configuration.quantity' => ['required', 'integer', 'between:1,2147483647'],
            'package_configuration.measurement_unit_id' => ['required', 'integer'],
        ])->validate();
        if (is_bool($input['quantity']) || is_bool($input['measurement_unit_id'])) {
            throw ValidationException::withMessages(['package_configuration' => 'Use a whole container count and a valid container unit.']);
        }
        if ($input['format'] === 'combined_contents' && (int) $input['quantity'] !== 1) {
            throw ValidationException::withMessages(['package_configuration.quantity' => 'All foods go inside one complete purchased container. Use a quantity of one.']);
        }
        if ($family->package_format !== null && $family->package_format !== $input['format'] && $family->products()->exists()) {
            throw ValidationException::withMessages(['package_configuration.format' => "{$family->name} already has packages. Create a separate service for a different food format."]);
        }
        $unit = MeasurementUnit::whereKey($input['measurement_unit_id'])->where('active', true)->lockForUpdate()->first();
        if (! $unit) {
            throw ValidationException::withMessages(['package_configuration.measurement_unit_id' => 'Choose an active container unit, or create one before configuring this service.']);
        }

        return ['package_format' => $input['format'], 'default_presentation_quantity' => (int) $input['quantity'],
            'default_presentation_unit_id' => $unit->id];
    }
}
