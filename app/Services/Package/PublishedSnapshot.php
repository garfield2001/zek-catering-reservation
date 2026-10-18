<?php

namespace App\Services\Package;

use App\Models\Dish;
use App\Models\DishCategory;
use App\Models\GroupOption;
use App\Models\PackageVersion;
use App\Models\Product;
use App\Models\SelectionGroup;
use App\Services\Catalog\PackageQuantityRules;
use Illuminate\Support\Facades\DB;

class PublishedSnapshot
{
    public function __construct(private PackageQuantityRules $quantities) {}

    public function capture(Product $product, PackageVersion $version): array
    {
        $version->load(['family', 'foodItems.category', 'requiredCategories', 'groups.options.offeringOption.offering.dish.category',
            'groups.categoryRules.category', 'groups.dishRules.dish']);
        $effective = $this->quantities->resolve($version->family, $version->only(
            'minimum_quantity', 'quantity_step', 'maximum_quantity'
        ));
        $fixedByDish = DB::table('package_version_food_items')
            ->where('package_version_id', $version->id)
            ->pluck('is_fixed', 'dish_id');

        return [
            'schema' => 5,
            'presentation' => app(PackagePresentation::class)->snapshot($version),
            'collection' => $version->family->only('id', 'code', 'name', 'description', 'behavior_type'),
            'product' => $product->only('id', 'code', 'name', 'description'),
            'package' => $product->only('id', 'code', 'name', 'description'),
            'version' => [
                'id' => $version->id, 'number' => $version->version_number,
                'currency' => 'PHP', 'base_price_minor' => (string) $version->base_price_minor,
                'price_basis' => $version->price_basis, 'selection_policy' => $version->selection_policy,
                ...$effective, 'estimated_capacity' => $version->estimated_capacity,
                'subgroup_minimum' => $version->subgroup_minimum,
                'subgroup_step' => $version->subgroup_step,
                'subgroup_maximum' => $version->subgroup_maximum,
                'total_selection_count' => $version->total_selection_count,
            ],
            'menu' => $version->usesFoodMenu() ? [
                'food_sizes' => app(PackageFoodSizes::class)->snapshot($version),
                'serving_default' => app(PackageServingDefaults::class)->snapshot($version),
                'total_selection_count' => $version->total_selection_count,
                'food_items' => $version->foodItems->map(fn (Dish $dish): array => [
                    'id' => $dish->id, 'name' => $dish->name, 'code' => $dish->code,
                    'category_id' => $dish->category_id, 'category_name' => $dish->category?->name,
                    'is_fixed' => (bool) $fixedByDish->get($dish->id), 'always_include' => (bool) $dish->pivot->getAttribute('always_include'),
                    'included_quantity' => $dish->pivot->getAttribute('included_quantity'), 'included_unit' => $dish->pivot->getAttribute('included_unit'),
                    'price_adjustment_minor' => (string) $dish->pivot->getAttribute('price_adjustment_minor'),
                ])->values()->all(),
                'required_categories' => $version->requiredCategories->map(fn (DishCategory $category): array => $category->only('id', 'code', 'name'))->values()->all(),
            ] : null,
            'groups' => $version->groups->sortBy([['sort_order', 'asc'], ['id', 'asc']])->map(fn (SelectionGroup $group): array => [
                ...$group->only('id', 'group_code', 'name', 'mode', 'slot_kind', 'minimum_count',
                    'maximum_count', 'unique_dishes', 'category_mode', 'inclusion_count', 'sort_order'),
                'options' => $group->options->map(function (GroupOption $choice): array {
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
                'category_rules' => $group->categoryRules->map(fn ($rule): array => [
                    ...$rule->only('category_id', 'minimum_count', 'maximum_count'),
                    'category_name' => $rule->category->name,
                ])->values()->all(),
                'food_item_rules' => $group->dishRules->map(fn ($rule): array => [
                    ...$rule->only('dish_id', 'effect'), 'food_item_name' => $rule->dish->name,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }
}
