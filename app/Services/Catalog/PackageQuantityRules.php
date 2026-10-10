<?php

namespace App\Services\Catalog;

use App\Models\FamilyOrderDefault;
use App\Models\ProductFamily;
use App\Models\VariantVersion;
use Illuminate\Validation\ValidationException;

class PackageQuantityRules
{
    public function resolve(ProductFamily $family, array $overrides): array
    {
        $default = FamilyOrderDefault::find($family->id);
        if (! $default) {
            throw ValidationException::withMessages(['family_id' => "{$family->name} has no ordering defaults."]);
        }
        if (! ($overrides['maximum_override_enabled'] ?? false) && ($overrides['maximum_override'] ?? null) !== null) {
            throw ValidationException::withMessages(['maximum_override' => 'Enable the maximum override before setting its value.']);
        }
        $minimum = $overrides['minimum_override'] ?? $default->minimum_quantity;
        $step = $overrides['step_override'] ?? $default->quantity_step;
        $maximum = ($overrides['maximum_override_enabled'] ?? false)
            ? ($overrides['maximum_override'] ?? null) : $default->maximum_quantity;
        if ($minimum < 1 || $step < 1 || ($maximum !== null && $maximum < $minimum)
            || ($default->enforce_minimum_floor && $minimum < $default->minimum_quantity)) {
            throw ValidationException::withMessages(['minimum_override' => "Quantity rules for {$family->name} conflict with its minimum of {$default->minimum_quantity} or its configured limits."]);
        }

        return ['minimum_quantity' => $minimum, 'quantity_step' => $step, 'maximum_quantity' => $maximum];
    }

    public function validate(VariantVersion $version, int $quantity): array
    {
        $rules = $this->resolve($version->family, $version->only(
            'minimum_override', 'step_override', 'maximum_override', 'maximum_override_enabled'
        ));
        if ($quantity < $rules['minimum_quantity']
            || ($quantity - $rules['minimum_quantity']) % $rules['quantity_step'] !== 0
            || ($rules['maximum_quantity'] !== null && $quantity > $rules['maximum_quantity'])) {
            $unit = match ($version->family->code) {
                'main_catering' => 'pax', 'food_pack' => 'packs', default => 'sets',
            };
            $maximum = $rules['maximum_quantity'] === null ? 'no maximum' : "maximum {$rules['maximum_quantity']} {$unit}";
            throw ValidationException::withMessages(['quantity' => "{$version->family->name} requires a minimum of {$rules['minimum_quantity']} {$unit}, increments of {$rules['quantity_step']}, and {$maximum}."]);
        }

        return $rules;
    }
}
