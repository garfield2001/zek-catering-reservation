<?php

namespace App\Services\Catalog;

use App\Models\GroupOption;
use App\Models\VariantVersion;
use Illuminate\Validation\ValidationException;

class PackagePricePreview
{
    public function __construct(
        private MinorMoney $money,
        private PackageQuantityRules $quantities,
        private PackageSelectionValidator $selections,
    ) {}

    public function calculate(VariantVersion $version, int $quantity, array $groups): array
    {
        $version->loadMissing(['family', 'variant.product']);
        if ($version->status !== 'draft' || ! $version->family->active || ! $version->variant->active
            || ! $version->variant->product->active) {
            throw ValidationException::withMessages(['package' => "{$version->family->name} or this draft is unavailable for a new preview."]);
        }
        $basis = match ($version->family->code) {
            'main_catering' => 'per_pax', 'food_pack' => 'per_pack',
            'belly_lechon', 'pica_pica' => 'per_set',
            default => throw ValidationException::withMessages(['package' => "{$version->family->name} does not use package previews."]),
        };
        if ($version->price_basis !== $basis || $version->currency !== 'PHP') {
            throw ValidationException::withMessages(['package' => 'The package price basis or currency is invalid.']);
        }
        $effective = $this->quantities->validate($version, $quantity);
        $allocations = $this->selections->validate($version, $quantity, $groups);
        $base = $this->money->parse($version->base_price_minor, 'base_price_minor');
        $total = 0;
        $breakdown = [];
        foreach ($allocations as $allocation) {
            $unitAdjustments = 0;
            $adjustments = [];
            foreach ($allocation['selections'] as $groupCode => $choices) {
                foreach ($choices as $choice) {
                    /** @var GroupOption $choice */
                    $amount = $this->money->parse($choice->price_adjustment_minor, 'price_adjustment_minor');
                    if ($version->family->code === 'pica_pica' && $amount !== 0) {
                        throw ValidationException::withMessages(['price_adjustment_minor' => "Fixed inclusions in {$version->family->name} cannot carry premiums."]);
                    }
                    $unitAdjustments = $this->money->add($unitAdjustments, $amount);
                    if ($amount > 0) {
                        $adjustments[] = ['group_code' => $groupCode,
                            'offering_option_id' => $choice->offering_option_id,
                            'unit_amount_minor' => (string) $amount,
                            'line_amount_minor' => (string) $this->money->multiply($amount, $allocation['quantity'])];
                    }
                }
            }
            $unitPrice = $this->money->add($base, $unitAdjustments);
            $lineTotal = $this->money->multiply($unitPrice, $allocation['quantity']);
            $total = $this->money->add($total, $lineTotal);
            $breakdown[] = ['quantity' => $allocation['quantity'], 'unit_base_minor' => (string) $base,
                'base_subtotal_minor' => (string) $this->money->multiply($base, $allocation['quantity']),
                'adjustments' => $adjustments, 'unit_total_minor' => (string) $unitPrice,
                'line_total_minor' => (string) $lineTotal];
        }

        return ['variant_version_id' => $version->id, 'status' => 'draft_preview',
            'price_basis' => $basis, 'currency' => 'PHP', 'quantity' => $quantity,
            'effective_quantity_rules' => $effective, 'groups' => $breakdown, 'total_minor' => (string) $total];
    }
}
