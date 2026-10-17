<?php

namespace Tests\Unit;

use App\Services\Catalog\CommercialComparison;
use PHPUnit\Framework\TestCase;

class CommercialComparisonTest extends TestCase
{
    public function test_comparison_ignores_regenerated_identifiers_but_reports_serving_food_and_quantity_changes(): void
    {
        $before = ['product' => ['name' => 'Mixed Package', 'description' => null],
            'version' => ['id' => 1, 'number' => 1, 'base_price_minor' => '100000', 'price_basis' => 'per_set', 'minimum_quantity' => 2, 'quantity_step' => 1, 'maximum_quantity' => null],
            'menu' => ['food_items' => [['id' => 10, 'name' => 'Chicken', 'is_fixed' => true]],
                'food_sizes' => [10 => ['measurement_unit' => ['name' => 'Tray'], 'choices' => [['id' => 1, 'quantity' => '1', 'size_label' => 'Small', 'is_default' => true, 'extra_price_minor' => '0']]]]], 'groups' => []];
        $after = $before;
        $after['version']['id'] = 2;
        $after['version']['number'] = 2;
        $after['menu']['food_sizes'][10]['choices'][0]['id'] = 25;
        $comparison = new CommercialComparison;
        $this->assertSame([], $comparison->between($before, $after));
        $after['version']['minimum_quantity'] = 3;
        $after['menu']['food_sizes'][10]['choices'][0]['size_label'] = 'Medium';
        $after['menu']['food_items'][0]['is_fixed'] = false;
        $changes = $comparison->between($before, $after);
        $this->assertCount(2, $changes);
        $this->assertSame(['label' => 'Minimum purchase', 'before' => '2', 'after' => '3'], $changes[0]);
        $this->assertStringContainsString('Included; 1 Small Tray', $changes[1]['before']);
        $this->assertStringContainsString('Customer choice; 1 Medium Tray', $changes[1]['after']);
    }
}
