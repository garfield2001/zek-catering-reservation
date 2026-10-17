<?php

namespace App\Services\Package;

use App\Models\MeasurementUnit;
use App\Models\PackageVersion;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Package purchase quantities never participate in this per-food configuration. */
class PackageServingDefaults
{
    public function normalize(PackageVersion $version, ?array $input): array
    {
        if ($input === null || $input === []) {
            return ['default_serving_quantity' => null, 'default_serving_unit_id' => null, 'default_serving_size_label' => null];
        }
        if ($version->family->behavior_type === 'main_package') {
            throw ValidationException::withMessages(['serving_default' => 'Catering Packages do not require per-food serving measurements.']);
        }
        Validator::make(['serving_default' => $input], [
            'serving_default' => ['array:quantity,measurement_unit_id,size_label'],
            'serving_default.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999.999', 'decimal:0,3'],
            'serving_default.measurement_unit_id' => ['required', 'integer'],
            'serving_default.size_label' => ['nullable', 'string', 'max:100'],
        ])->validate();
        $unit = MeasurementUnit::whereKey($input['measurement_unit_id'])->where('active', true)->lockForUpdate()->first();
        if (! $unit) {
            throw ValidationException::withMessages(['serving_default.measurement_unit_id' => 'Choose an active Measurement Unit for the package default.']);
        }

        $label = trim($input['size_label'] ?? '');

        return ['default_serving_quantity' => number_format((float) $input['quantity'], 3, '.', ''),
            'default_serving_unit_id' => $unit->id, 'default_serving_size_label' => $label === '' ? null : $label];
    }

    public function input(PackageVersion $version): ?array
    {
        return $version->default_serving_unit_id === null ? null : [
            'quantity' => $version->default_serving_quantity,
            'measurement_unit_id' => $version->default_serving_unit_id,
            'size_label' => $version->default_serving_size_label,
        ];
    }

    public function snapshot(PackageVersion $version): ?array
    {
        $input = $this->input($version);
        if ($input === null) {
            return null;
        }

        return ['quantity' => $input['quantity'], 'size_label' => $input['size_label'],
            'measurement_unit' => MeasurementUnit::findOrFail($input['measurement_unit_id'])->only('id', 'name', 'abbreviation')];
    }

    public function validateDraft(PackageVersion $version): void
    {
        $this->normalize($version, $this->input($version));
    }

    /** Resolve once per preview; published measurements never consult renamed master units. */
    public function effectiveDefault(PackageVersion $version): ?array
    {
        return $version->status === 'published'
            ? ($version->snapshot()['menu']['serving_default'] ?? null)
            : $this->snapshot($version);
    }

    /** A custom choice or legacy explicit measurement takes precedence over inheritance. */
    public function resolve(?array $default, ?array $size, mixed $quantity, ?string $unit): array
    {
        if ($size !== null) {
            return ['quantity_per_unit' => $size['quantity'], 'unit' => $size['measurement_unit']['abbreviation'],
                'size_label' => $size['size_label'] ?? null, 'serving_source' => 'custom'];
        }
        if ($quantity !== null || $unit !== null) {
            return ['quantity_per_unit' => $quantity, 'unit' => $unit, 'size_label' => null, 'serving_source' => 'custom'];
        }

        return ['quantity_per_unit' => $default['quantity'] ?? null, 'unit' => $default['measurement_unit']['abbreviation'] ?? null,
            'size_label' => $default['size_label'] ?? null, 'serving_source' => $default === null ? 'unspecified' : 'package_default'];
    }
}
