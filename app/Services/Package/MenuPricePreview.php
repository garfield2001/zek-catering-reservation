<?php

namespace App\Services\Package;

use App\Models\PackageVersion;
use App\Services\Catalog\MinorMoney;
use App\Services\Catalog\PackageQuantityRules;
use Illuminate\Validation\ValidationException;

class MenuPricePreview
{
    public function __construct(private PackageMenuConfiguration $menus, private MinorMoney $money, private PackageQuantityRules $quantities) {}

    public function calculate(PackageVersion $version, int $quantity, array $groups, array $effective): array
    {
        if ($groups === [] || ($version->selection_policy !== 'split' && count($groups) !== 1)) {
            throw ValidationException::withMessages(['groups' => 'Provide one complete menu, or enable different meal groups for this package.']);
        }
        $base = $this->money->parse($version->base_price_minor, 'base_price_minor');
        $total = 0;
        $allocated = 0;
        $breakdown = [];
        $menus = [];
        foreach ($groups as $group) {
            $count = $group['quantity'] ?? null;
            if (! is_int($count) || $count < 1 || $count > $quantity - $allocated || ! is_array($group['selections']['food_items'] ?? null)) {
                throw ValidationException::withMessages(['groups' => 'Each meal group needs a positive whole quantity and a complete menu.']);
            }
            $allocated += $count;
            $ids = $this->menus->validateCustomerSelection($version, $group['selections']['food_items']);
            sort($ids);
            $key = implode(',', $ids);
            $menus[$key] = ['quantity' => ($menus[$key]['quantity'] ?? 0) + $count, 'ids' => $ids];
        }
        if ($allocated !== $quantity) {
            throw ValidationException::withMessages(['groups' => 'Meal group quantities must add up to the total purchase quantity.']);
        }
        foreach (array_values($menus) as $index => $menu) {
            $count = $menu['quantity'];
            $ids = $menu['ids'];
            if ($version->selection_policy === 'split') {
                $this->quantities->validateSubgroup($version, $count, "groups.$index.quantity");
            }
            $unit = $base;
            $adjustments = [];
            $contents = [];
            foreach ($version->foodItems->whereIn('id', $ids) as $food) {
                $amount = $this->money->parse($food->pivot->getAttribute('price_adjustment_minor'), 'food_details');
                $unit = $this->money->add($unit, $amount);
                if ($amount > 0) {
                    $adjustments[] = ['food_item_id' => $food->id, 'unit_amount_minor' => (string) $amount,
                        'line_amount_minor' => (string) $this->money->multiply($amount, $count)];
                }
                $contents[] = ['food_item_id' => $food->id, 'name' => $food->name,
                    'quantity_per_unit' => $food->pivot->getAttribute('included_quantity'), 'unit' => $food->pivot->getAttribute('included_unit'),
                    'purchased_units' => $count];
            }
            $line = $this->money->multiply($unit, $count);
            $total = $this->money->add($total, $line);
            $breakdown[] = ['quantity' => $count, 'food_items' => $contents, 'unit_base_minor' => (string) $base,
                'base_subtotal_minor' => (string) $this->money->multiply($base, $count),
                'adjustments' => $adjustments, 'unit_total_minor' => (string) $unit, 'line_total_minor' => (string) $line];
        }

        return ['package_version_id' => $version->id, 'status' => 'draft_preview',
            'price_basis' => $version->price_basis, 'currency' => 'PHP', 'quantity' => $quantity,
            'effective_quantity_rules' => $effective, 'groups' => $breakdown, 'total_minor' => (string) $total];
    }
}
