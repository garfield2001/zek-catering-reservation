<?php

namespace App\Enums\Catalog;

enum CollectionBehavior: string
{
    case MainPackage = 'main_package';
    case Package = 'package';
    case PackedMeal = 'packed_meal';
    case DirectSale = 'direct_sale';
    case FixedBundle = 'fixed_bundle';

    public function label(): string
    {
        return match ($this) {
            self::MainPackage => 'Main catering packages',
            self::Package => 'Package-based add-on',
            self::PackedMeal => 'Packed meals',
            self::DirectSale => 'Direct-sale items',
            self::FixedBundle => 'Fixed bundles',
        };
    }

    public function priceBasis(): ?string
    {
        return match ($this) {
            self::MainPackage => 'per_pax',
            self::PackedMeal => 'per_pack',
            self::Package, self::FixedBundle => 'per_set',
            self::DirectSale => null,
        };
    }

    public function quantityUnit(): string
    {
        return match ($this) {
            self::MainPackage => 'pax',
            self::PackedMeal => 'packs',
            self::Package, self::FixedBundle => 'sets',
            self::DirectSale => 'items',
        };
    }

    public function supportsPackageDrafts(): bool
    {
        return $this !== self::DirectSale;
    }

    public function supportsSplit(): bool
    {
        return $this === self::PackedMeal;
    }
}
