<?php

namespace App\Services\Package;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantVersion;
use App\Services\Catalog\PackageQuantityRules;

class PublishedSnapshot
{
    public function __construct(private PackageQuantityRules $quantities) {}

    public function capture(Product $product, ProductVariant $variant, VariantVersion $version): array
    {
        $version->load(['family', 'groups.options.offeringOption.offering.dish.category',
            'groups.categoryRules.category', 'groups.dishRules.dish']);
        $effective = $this->quantities->resolve($version->family, $version->only(
            'minimum_override', 'step_override', 'maximum_override', 'maximum_override_enabled'
        ));

        return [
            'schema' => 1,
            'collection' => $version->family->only('id', 'code', 'name', 'description', 'behavior_type'),
            'product' => $product->only('id', 'code', 'name', 'description'),
            'variant' => $variant->only('id', 'code', 'name', 'description'),
            'version' => [
                'id' => $version->id, 'number' => $version->version_number,
                'currency' => 'PHP', 'base_price_minor' => (string) $version->base_price_minor,
                'price_basis' => $version->price_basis, 'selection_policy' => $version->selection_policy,
                'minimum_override' => $version->minimum_override, 'step_override' => $version->step_override,
                'maximum_override_enabled' => $version->maximum_override_enabled,
                'maximum_override' => $version->maximum_override, ...$effective,
                'subgroup_minimum' => $version->subgroup_minimum,
                'subgroup_step' => $version->subgroup_step,
                'subgroup_maximum' => $version->subgroup_maximum,
            ],
            'groups' => $version->groups->sortBy([['sort_order', 'asc'], ['id', 'asc']])->map(fn ($group) => [
                ...$group->only('id', 'group_code', 'name', 'mode', 'slot_kind', 'minimum_count',
                    'maximum_count', 'unique_dishes', 'category_mode', 'inclusion_count', 'sort_order'),
                'options' => $group->options->map(function ($choice) {
                    $option = $choice->offeringOption;
                    $offering = $option->offering;
                    $dish = $offering->dish;

                    return [
                        'group_option_id' => $choice->id, 'offering_option_id' => $option->id,
                        'is_default' => $choice->is_default,
                        'price_adjustment_minor' => (string) $choice->price_adjustment_minor,
                        'offering' => $offering->only('id', 'code', 'name'),
                        'food_item' => $dish->only('id', 'code', 'name', 'description', 'category_id'),
                        'food_category' => $dish->category->only('id', 'code', 'name'),
                        'serving_option' => $option->only('id', 'option_code', 'label', 'unit', 'quantity',
                            'serving_pax', 'serving_claim'),
                    ];
                })->values()->all(),
                'category_rules' => $group->categoryRules->map(fn ($rule) => [
                    ...$rule->only('category_id', 'minimum_count', 'maximum_count'),
                    'category_name' => $rule->category->name,
                ])->values()->all(),
                'food_item_rules' => $group->dishRules->map(fn ($rule) => [
                    ...$rule->only('dish_id', 'effect'), 'food_item_name' => $rule->dish->name,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }
}
