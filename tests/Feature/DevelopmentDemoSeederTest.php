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
use App\Models\ReservationRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\Catalog\PackageQuantityRules;
use App\Services\Package\PackageMenuConfiguration;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DevelopmentDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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

    public function test_opt_in_demo_seeder_creates_a_published_main_catering_catalog_ready_for_customer_selection(): void
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

        foreach (['Package A' => ['38000', 4], 'Package B' => ['45000', 5], 'Package C' => ['48000', 6], 'Package D' => ['52000', 7]] as $name => [$price, $total]) {
            $product = Product::query()->where('family_id', $this->mainCatering->id)->where('name', $name)->sole();
            $draft = $product->versions()->sole();
            $this->assertSame('published', $draft->status);
            $this->assertSame($draft->id, $product->current_version_id);
            $this->assertSame($price, (string) $draft->base_price_minor);
            $this->assertSame('per_pax', $draft->price_basis);
            $this->assertSame(150, $draft->minimum_quantity);
            $this->assertSame(50, $draft->quantity_step);
            $this->assertNull($draft->maximum_quantity);
            $this->assertNotNull($draft->published_snapshot);
            $this->assertSame($total, $draft->total_selection_count);
            $this->assertSame(32, $draft->foodItems()->count());
            $rules = $draft->requiredCategories()->get()->keyBy('name');
            $this->assertSame(1, $rules->get('Rice')->pivot->minimum_count);
            $this->assertSame(1, $rules->get('Rice')->pivot->maximum_count);
            $this->assertSame(1, $rules->get('Drinks')->pivot->minimum_count);
            $this->assertSame(1, $rules->get('Drinks')->pivot->maximum_count);
            $this->assertFalse($draft->foodItems()->wherePivot('is_fixed', true)->exists());
            $selected = $draft->foodItems()
                ->whereHas('category', fn ($query) => $query->where('name', 'Rice'))
                ->limit(1)->pluck('dishes.id')->all();
            $selected = [...$selected, ...$draft->foodItems()
                ->whereHas('category', fn ($query) => $query->where('name', 'Drinks'))
                ->limit(1)->pluck('dishes.id')->all()];
            $selected = [...$selected, ...$draft->foodItems()
                ->whereHas('category', fn ($query) => $query->whereIn('name', ['Beef', 'Chicken', 'Pork', 'Desserts']))
                ->limit($total - 2)->pluck('dishes.id')->all()];
            $this->assertCount($total, app(PackageMenuConfiguration::class)->validateCustomerSelection($draft->fresh(['family', 'product']), $selected));
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

    public function test_every_seeded_main_package_validates_customer_choices_and_can_enter_the_normal_inquiry_to_quotation_flow(): void
    {
        $this->seed(DevelopmentDemoSeeder::class);

        foreach (['Package A' => 4, 'Package B' => 5, 'Package C' => 6, 'Package D' => 7] as $name => $total) {
            $product = Product::where('name', $name)->sole();
            $version = $product->currentVersion()->firstOrFail();
            $selected = $this->validFoodSelection($version, $total);
            $withoutRice = $this->validFoodSelection($version, $total, without: 'Rice');
            $withoutDrinks = $this->validFoodSelection($version, $total, without: 'Drinks');

            foreach ([$withoutRice, $withoutDrinks, array_slice($selected, 0, $total - 1), array_fill(0, $total, $selected[0])] as $invalid) {
                try {
                    app(PackageMenuConfiguration::class)->validateCustomerSelection($version, $invalid);
                    $this->fail("{$name} accepted an incomplete or duplicate customer menu.");
                } catch (ValidationException $exception) {
                    $this->assertArrayHasKey('food_items', $exception->errors());
                }
            }

            $groups = [['quantity' => 150, 'selections' => ['food_items' => $selected, 'food_sizes' => []]]];
            $this->postJson("/catering-packages/{$product->id}/selection", ['quantity' => 150, 'groups' => $groups])
                ->assertOk()
                ->assertJsonPath('selection_count', 1);
            $this->get('/reservation-request')->assertOk();
            $this->post('/reservation-request', [
                'idempotency_key' => (string) Str::uuid(),
                'full_name' => 'Demo Customer '.$name,
                'mobile_number' => '09171234567',
                'email' => strtolower(str_replace(' ', '.', $name)).'@example.test',
                'event_date' => now('Asia/Manila')->addDays(20 + $total)->toDateString(),
                'event_start_time' => '10:00',
                'venue_information' => 'Demo venue',
            ])->assertRedirect();
            $request = ReservationRequest::query()->where('customer_name', 'Demo Customer '.$name)->with('lines')->sole();
            $this->assertSame($version->id, $request->main_package_version_id);
            $this->assertSame($selected, $request->lines->sole()->commercial_snapshot['customer_selection']['groups'][0]['selections']['food_items']);
            $this->actingAs($this->administrator)
                ->withSession(['access_version' => $this->administrator->access_version])
                ->post("/admin/reservation-requests/{$request->id}/quotations")
                ->assertRedirect();
            $this->assertTrue($request->quotation()->exists());
        }
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

    /** @return list<int> */
    private function validFoodSelection(PackageVersion $version, int $total, ?string $without = null): array
    {
        $foods = $version->foodItems()->with('category')->get();
        $selected = collect();
        foreach (['Rice', 'Drinks'] as $category) {
            if ($category !== $without) {
                $selected->push($foods->first(fn (Dish $food) => $food->category->name === $category));
            }
        }
        $otherCategories = ['Beef', 'Chicken', 'Pork', 'Desserts'];
        $requiredOther = $total - $selected->count();
        $selected = $selected->merge($foods->filter(fn (Dish $food) => in_array($food->category->name, $otherCategories, true))->take($requiredOther));

        return $selected->map(fn (Dish $food) => $food->id)->all();
    }
}
