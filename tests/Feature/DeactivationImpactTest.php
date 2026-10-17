<?php

namespace Tests\Feature;

use App\Models\Dish;
use App\Models\DishCategory;
use App\Models\MeasurementUnit;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeactivationImpactTest extends TestCase
{
    use RefreshDatabase;

    public function test_impact_preview_is_read_only_and_requires_catalog_management(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('code', 'admin')->sole());
        $category = DishCategory::create(['code' => 'impact_food', 'name' => 'Impact Foods', 'active' => true]);
        $food = Dish::create(['code' => 'impact', 'name' => 'Impact Food', 'category_id' => $category->id, 'active' => true]);
        $unit = MeasurementUnit::create(['name' => 'Bowls', 'abbreviation' => 'bwl', 'active' => true]);
        $this->actingAs($admin)->withSession(['access_version' => $admin->access_version]);
        foreach (['category' => $category, 'food' => $food, 'unit' => $unit] as $kind => $record) {
            $this->getJson(route('catalog.deactivation-impact', [$kind, $record->id]))->assertOk()->assertJsonStructure(['messages', 'usage']);
            $this->assertTrue($record->fresh()->active);
        }
        $this->getJson(route('catalog.deactivation-impact', ['unknown', 1]))->assertNotFound();
        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('code', 'staff')->sole());
        $this->actingAs($staff)->withSession(['access_version' => $staff->access_version]);
        $this->getJson(route('catalog.deactivation-impact', ['food', $food->id]))->assertForbidden();
    }
}
