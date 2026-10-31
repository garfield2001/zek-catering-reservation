<?php

namespace Tests\Feature;

use App\Models\Dish;
use App\Models\DishCategory;
use App\Queries\Catalog\FoodDirectory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LegacyCollections;
use Tests\TestCase;

class FoodDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        LegacyCollections::seed();
    }

    public function test_it_sorts_the_full_filtered_catalog_and_honours_all_rows(): void
    {
        $beef = DishCategory::create(['code' => 'beef', 'name' => 'Beef', 'sort_order' => 1, 'active' => true]);
        $chicken = DishCategory::create(['code' => 'chicken', 'name' => 'Chicken', 'sort_order' => 2, 'active' => true]);
        $inactive = DishCategory::create(['code' => 'inactive', 'name' => 'Inactive', 'sort_order' => 3, 'active' => false]);
        Dish::create(['code' => 'alpha', 'name' => 'Alpha', 'category_id' => $chicken->id, 'active' => true]);
        Dish::create(['code' => 'bravo', 'name' => 'Bravo', 'category_id' => $beef->id, 'active' => true]);
        Dish::create(['code' => 'charlie', 'name' => 'Charlie', 'category_id' => $inactive->id, 'active' => true]);

        $directory = app(FoodDirectory::class);
        $byCategory = $directory->search(['sort' => 'category', 'direction' => 'asc', 'per_page' => '5']);
        $this->assertSame(['Bravo', 'Alpha', 'Charlie'], $byCategory->getCollection()->pluck('name')->all());

        $descending = $directory->search(['sort' => 'name', 'direction' => 'desc', 'per_page' => '5']);
        $this->assertSame(['Charlie', 'Bravo', 'Alpha'], $descending->getCollection()->pluck('name')->all());

        $availableFirst = $directory->search(['sort' => 'availability', 'direction' => 'desc', 'per_page' => '5']);
        $this->assertSame(['Alpha', 'Bravo', 'Charlie'], $availableFirst->getCollection()->pluck('name')->all());

        $all = $directory->search(['sort' => 'name', 'direction' => 'asc', 'per_page' => 'all']);
        $this->assertSame(3, $all->total());
        $this->assertCount(3, $all->items());
    }
}
