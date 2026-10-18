<?php

namespace App\Services\Catalog;

use App\Models\PackageVersion;
use App\Models\ProductFamily;
use App\Services\Package\PackagePresentation;
use Illuminate\Validation\ValidationException;

class PackageQuantityRules
{
    /** Validate independent values; Collection defaults never constrain a Package. */
    public function resolve(ProductFamily $family, array $values): array
    {
        $minimum = $values['minimum_quantity'] ?? 1;
        $step = $values['quantity_step'] ?? 1;
        $maximum = $values['maximum_quantity'] ?? null;
        foreach (['minimum_quantity' => $minimum, 'quantity_step' => $step, 'maximum_quantity' => $maximum] as $key => $value) {
            if ($value !== null && (filter_var($value, FILTER_VALIDATE_INT) === false || is_bool($value) || $value < 1 || $value > 2147483647)) {
                throw ValidationException::withMessages([$key => 'Enter a positive whole quantity within the supported range.']);
            }
        }
        if ($maximum !== null && $maximum < $minimum) {
            throw ValidationException::withMessages(['maximum_quantity' => 'Maximum purchase must be at least the minimum purchase.']);
        }

        return ['minimum_quantity' => (int) $minimum, 'quantity_step' => (int) $step,
            'maximum_quantity' => $maximum === null ? null : (int) $maximum];
    }

    public function validateSubgroup(PackageVersion $version, int $quantity, string $field): void
    {
        if ($version->subgroup_minimum !== null
            && ($quantity < $version->subgroup_minimum
                || ($quantity - $version->subgroup_minimum) % $version->subgroup_step !== 0
                || ($version->subgroup_maximum !== null && $quantity > $version->subgroup_maximum))) {
            $maximum = $version->subgroup_maximum === null ? 'no maximum' : "maximum {$version->subgroup_maximum}";
            throw ValidationException::withMessages([$field => "This allocation must meet minimum {$version->subgroup_minimum}, increment {$version->subgroup_step}, and {$maximum}."]);
        }
    }

    public function validate(PackageVersion $version, int $quantity): array
    {
        $rules = $this->resolve($version->family, $version->only('minimum_quantity', 'quantity_step', 'maximum_quantity'));
        if ($quantity < $rules['minimum_quantity']
            || ($quantity - $rules['minimum_quantity']) % $rules['quantity_step'] !== 0
            || ($rules['maximum_quantity'] !== null && $quantity > $rules['maximum_quantity'])) {
            $purchaseUnit = app(PackagePresentation::class)->purchaseUnit($version);
            $unit = $purchaseUnit === null ? $version->family->behavior()->quantityUnit() : $purchaseUnit.' units';
            $maximum = $rules['maximum_quantity'] === null ? 'no maximum' : "maximum {$rules['maximum_quantity']} {$unit}";
            throw ValidationException::withMessages(['quantity' => "{$version->family->name} — {$version->product->name} requires a minimum of {$rules['minimum_quantity']} {$unit}, increments of {$rules['quantity_step']}, and {$maximum}."]);
        }

        return $rules;
    }
}
