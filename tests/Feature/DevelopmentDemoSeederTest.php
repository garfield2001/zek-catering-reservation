<?php

namespace Tests\Feature;

use App\Actions\Catalog\EnsurePrimaryService;
use App\Actions\Package\PublishPackageVersion;
use App\Actions\Package\SavePackageMenuConfiguration;
use App\Actions\Package\SaveProduct;
use App\Models\Dish;
use App\Models\DishCategory;
use App\Models\PackageVersion;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\Role;
use App\Models\User;
use App\Services\Catalog\PackageQuantityRules;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DevelopmentDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DevelopmentDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    private ProductFamily $mainCatering;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->administrator = User::factory()->create(['active' => true]);
        $this->administrator->roles()->attach(Role::where('code', 'admin')->sole());
        $this->mainCatering = app(EnsurePrimaryService::class)->handle();
    }

    public function test_opt_in_demo_seeder_creates_the_exact_catalog_and_incomplete_main_catering_drafts(): void
    {
        $this->seed(DevelopmentDemoSeeder::class);

        $expected = [
            'Chicken' => 5,
            'Beef' => 6,
            'Pork' => 6,
            'Desserts' => 6,
            'Drinks' => 6,
            'Rice' => 3,
            'Lechon' => 1,
            'Vegetables' => 1,
            'Pasta & Noodles' => 2,
            'Finger Foods & Snacks' => 5,
            'Bread & Sides' => 1,
        ];
        $categories = DishCategory::query()->where('code', 'like', 'category_demo_%')->get()->keyBy('name');
        $this->assertCount(11, $categories);
        $foods = Dish::query()->where('code', 'like', 'food_demo_%')->get();
        $this->assertCount(42, $foods);
        $this->assertSame('Lumpia Shanghai', $foods->firstWhere('name', 'Lumpia Shanghai')?->name);
        $this->assertSame('Lumpia', $foods->firstWhere('name', 'Lumpia')?->name);
        foreach ($expected as $categoryName => $count) {
            $category = $categories->get($categoryName);
            $this->assertNotNull($category);
            $this->assertTrue($category->active);
            $items = $foods->where('category_id', $category->id);
            $this->assertCount($count, $items);
            $this->assertTrue($items->every(fn (Dish $food) => $food->active));
        }

        foreach (['Package A' => '38000', 'Package B' => '45000', 'Package C' => '48000', 'Package D' => '52000'] as $name => $price) {
            $product = Product::query()->where('family_id', $this->mainCatering->id)->where('name', $name)->sole();
            $draft = $product->versions()->sole();
            $this->assertSame('draft', $draft->status);
            $this->assertNull($product->current_version_id);
            $this->assertSame($price, (string) $draft->base_price_minor);
            $this->assertSame('per_pax', $draft->price_basis);
            $this->assertSame(150, $draft->minimum_quantity);
            $this->assertSame(50, $draft->quantity_step);
            $this->assertNull($draft->maximum_quantity);
            $this->assertNull($draft->published_snapshot);
            $this->assertSame(0, $draft->foodItems()->count());
            $this->assertInvalidQuantity($draft, 175);
        }
    }

    public function test_demo_seeder_is_idempotent_and_does_not_overwrite_identified_or_unrelated_records(): void
    {
        $unrelatedCategory = DishCategory::create([
            'code' => 'owner_foods', 'name' => 'Owner Foods', 'active' => false, 'sort_order' => 7,
        ]);
        $unrelatedFood = Dish::create([
            'code' => 'owner_recipe', 'name' => 'Owner Recipe', 'category_id' => $unrelatedCategory->id, 'active' => false,
        ]);
        $historical = $this->publishedPackage();

        $this->seed(DevelopmentDemoSeeder::class);
        $firstCatalogCount = Dish::where('code', 'like', 'food_demo_%')->count();
        $firstPackageCount = Product::whereNotNull('creation_token')->count();
        $demo = Product::where('name', 'Package A')->sole();
        $demo->update(['active' => false, 'name' => 'Owner renamed Package A']);
        $historicalSnapshot = $historical->fresh()->snapshot();

        $this->seed(DevelopmentDemoSeeder::class);

        $this->assertSame($firstCatalogCount, Dish::where('code', 'like', 'food_demo_%')->count());
        $this->assertSame($firstPackageCount, Product::whereNotNull('creation_token')->count());
        $this->assertFalse($demo->fresh()->active);
        $this->assertSame('Owner renamed Package A', $demo->fresh()->name);
        $this->assertSame($historicalSnapshot, $historical->fresh()->snapshot());
        $this->assertFalse($unrelatedCategory->fresh()->active);
        $this->assertFalse($unrelatedFood->fresh()->active);
    }

    private function publishedPackage(): PackageVersion
    {
        $product = app(SaveProduct::class)->handle($this->administrator, [
            'family_id' => $this->mainCatering->id, 'name' => 'Historical owner package', 'active' => true,
        ]);
        $categories = collect();
        $foods = collect();
        foreach (['Owner Staple' => ['Owner Rice'], 'Owner Drinks' => ['Owner Water', 'Owner Juice'], 'Owner Main' => ['Owner Beef', 'Owner Chicken']] as $categoryName => $names) {
            $category = DishCategory::create([
                'code' => str($categoryName)->slug('_'), 'name' => $categoryName, 'active' => true, 'sort_order' => 0,
            ]);
            $categories->push($category);
            foreach ($names as $name) {
                $foods->push(Dish::create([
                    'code' => str($name)->slug('_'), 'name' => $name, 'category_id' => $category->id, 'active' => true,
                ]));
            }
        }
        $draft = $product->versions()->sole();
        app(SavePackageMenuConfiguration::class)->handle($this->administrator, $draft, [
            'draft_revision' => $draft->draft_revision,
            'total_selection_count' => 3,
            'food_item_ids' => $foods->pluck('id')->all(),
            'required_category_ids' => $categories->take(2)->pluck('id')->all(),
        ]);

        return app(PublishPackageVersion::class)->handle($this->administrator, $draft->fresh(), $draft->fresh()->draft_revision);
    }

    private function assertInvalidQuantity(PackageVersion $version, int $quantity): void
    {
        try {
            app(PackageQuantityRules::class)->validate($version->fresh(['family', 'product']), $quantity);
            $this->fail('The demo package accepted an invalid guest quantity.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
        }
    }
}
