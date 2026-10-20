<?php

namespace App\Services\Catalog;

use App\Models\MeasurementUnit;
use Illuminate\Support\Facades\DB;

class MeasurementUnitUsage
{
    /** @return array<string, int> */
    public function counts(MeasurementUnit $unit): array
    {
        return [
            'service_defaults' => DB::table('product_families')->where('default_presentation_unit_id', $unit->id)->count(),
            'package_presentations' => DB::table('package_versions')->where('presentation_unit_id', $unit->id)->count(),
            'food_presentations' => DB::table('package_version_food_items')->where('presentation_unit_id', $unit->id)->count(),
            'package_portions' => DB::table('package_versions')->where('default_serving_unit_id', $unit->id)->count(),
            // Food portions intentionally store the resolved abbreviation as a
            // frozen fact rather than an FK. Count that historical label too,
            // so an Administrator cannot delete or repurpose its source unit.
            'food_portion_labels' => DB::table('package_version_food_items')
                ->whereRaw('lower(btrim(included_unit)) = lower(btrim(?))', [$unit->abbreviation])
                ->count(),
            'food_sizes' => DB::table('package_food_size_groups')->where('measurement_unit_id', $unit->id)->count(),
        ];
    }

    public function isUsed(MeasurementUnit $unit): bool
    {
        return collect($this->counts($unit))->sum() > 0;
    }

    public function summary(MeasurementUnit $unit): string
    {
        $total = array_sum($this->counts($unit));

        return $total === 1 ? '1 saved commercial configuration' : "{$total} saved commercial configurations";
    }
}
