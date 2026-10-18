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
        $servings = app(PackageServingDefaults::class);
        $servingDefault = $servings->effectiveDefault($version);
        $presentation = app(PackagePresentation::class)->effective($version);
        foreach ($groups as $group) {
            $count = $group['quantity'] ?? null;
            if (! is_int($count) || $count < 1 || $count > $quantity - $allocated || ! is_array($group['selections']['food_items'] ?? null)) {
                throw ValidationException::withMessages(['groups' => 'Each meal group needs a positive whole quantity and a complete menu.']);
            }
            $allocated += $count;
            $ids = $this->menus->validateCustomerSelection($version, $group['selections']['food_items']);
            $sizeSelections = $group['selections']['food_sizes'] ?? [];
            if (! is_array($sizeSelections)) {
                throw ValidationException::withMessages(['food_sizes' => 'Provide a size choice for each applicable food.']);
            }
            $sizes = app(PackageFoodSizes::class)->resolve($version, $ids, $sizeSelections);
            sort($ids);
            $key = implode(',', $ids).':'.json_encode(array_column($sizes, 'id'), JSON_THROW_ON_ERROR);
            $menus[$key] = ['quantity' => ($menus[$key]['quantity'] ?? 0) + $count, 'ids' => $ids, 'sizes' => $sizes];
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
                $size = $menu['sizes'][$food->id] ?? null;
                if ($size) {
                    $amount = $this->money->add($amount, $this->money->parse($size['extra_price_minor'], 'food_sizes'));
                }
                $unit = $this->money->add($unit, $amount);
                if ($amount > 0) {
                    $adjustments[] = ['food_item_id' => $food->id, 'unit_amount_minor' => (string) $amount,
                        'line_amount_minor' => (string) $this->money->multiply($amount, $count)];
                }
                $contents[] = ['food_item_id' => $food->id, 'name' => $food->name,
                    'presentation' => ($presentation['format'] ?? null) === 'separate_servings' ? ($presentation['food_overrides'][$food->id] ?? $presentation['default']) : null,
                    'size_choice' => $size,
                    ...$servings->resolve($servingDefault, $size, $food->pivot->getAttribute('included_quantity'), $food->pivot->getAttribute('included_unit')),
                    'purchased_units' => $count];
            }
            $line = $this->money->multiply($unit, $count);
            $total = $this->money->add($total, $line);
            $breakdown[] = ['quantity' => $count, 'food_items' => $contents, 'unit_base_minor' => (string) $base,
                'base_subtotal_minor' => (string) $this->money->multiply($base, $count),
                'adjustments' => $adjustments, 'unit_total_minor' => (string) $unit, 'line_total_minor' => (string) $line];
        }

        return ['package_version_id' => $version->id, 'status' => 'draft_preview',
            'presentation' => $presentation, 'purchase_unit_label' => app(PackagePresentation::class)->purchaseUnit($version), 'price_basis' => $version->price_basis, 'currency' => 'PHP', 'quantity' => $quantity,
            'effective_quantity_rules' => $effective, 'groups' => $breakdown, 'total_minor' => (string) $total];
    }
}
