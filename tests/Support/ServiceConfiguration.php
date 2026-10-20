<?php

namespace Tests\Support;

use App\Models\MeasurementUnit;
use App\Models\ProductFamily;

/** Explicit fixtures for service-owned formats; legacy scenarios opt out. */
class ServiceConfiguration
{
    public static function input(): array
    {
        $unit = MeasurementUnit::firstOrCreate(['name' => 'Test Container'], ['abbreviation' => 'test-container', 'active' => true]);

        return ['format' => 'separate_servings', 'quantity' => 1, 'measurement_unit_id' => $unit->id];
    }

    public static function configure(ProductFamily $family): void
    {
        $input = self::input();
        $family->forceFill(['package_format' => $input['format'], 'default_presentation_quantity' => 1, 'default_presentation_unit_id' => $input['measurement_unit_id']])->save();
    }
}
