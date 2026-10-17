<?php

namespace Tests\Feature;

use App\Actions\Package\ClonePackageDraft;
use App\Actions\Package\PublishPackageVersion;
use App\Actions\Package\SavePackageMenuConfiguration;
use App\Actions\Package\SavePackageVersion;
use App\Actions\Package\SaveProduct;
use App\Models\Dish;
use App\Models\DishCategory;
use App\Models\ProductFamily;
use App\Models\Role;
use App\Models\User;
use App\Services\Package\PackageMenuConfiguration;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\LegacyCollections;
use Tests\TestCase;

class MainCateringPackageWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private ProductFamily $family;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        LegacyCollections::seed();
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::where('code', 'admin')->sole());
        $this->family = ProductFamily::where('code', 'main_catering')->sole();
    }

    public function test_main_catering_menu_is_package_owned_exact_and_publishable_without_serving_options(): void
    {
        $categories = collect(['Rice', 'Drinks', 'Beef', 'Chicken', 'Other Foods'])->mapWithKeys(function (string $name): array {
            $category = DishCategory::create(['code' => strtolower(str_replace(' ', '_', $name)), 'name' => $name, 'active' => true]);

            return [$name => $category];
        });
        $items = collect([
            ['Fried Rice', 'Rice'], ['Steamed Rice', 'Rice'], ['Cola', 'Drinks'], ['Juice', 'Drinks'],
            ['Beef Caldereta', 'Beef'], ['Beef Steak', 'Beef'], ['Chicken Adobo', 'Chicken'], ['Chicken BBQ', 'Chicken'], ['Spring Rolls', 'Other Foods'],
        ])->map(function (array $row) use ($categories): Dish {
            $dish = Dish::create(['code' => strtolower(str_replace(' ', '_', $row[0])), 'name' => $row[0], 'category_id' => $categories[$row[1]]->id, 'active' => true]);

            return $dish;
        });
        $products = collect(['Package A' => 4, 'Package B' => 5, 'Package C' => 6])->mapWithKeys(fn (int $count, string $name): array => [
            $name => app(SaveProduct::class)->handle($this->admin, ['family_id' => $this->family->id, 'name' => $name, 'description' => null, 'active' => true, 'creation_token' => (string) str()->uuid()]),
        ]);
        foreach (['Package A' => 4, 'Package B' => 5, 'Package C' => 6] as $name => $count) {
            $package = $products[$name];
            $draft = $package->versions()->sole();
            app(SavePackageVersion::class)->handle($this->admin, $package, [
                'base_price_pesos' => $name === 'Package B' ? '850.00' : '750.00',
                'selection_policy' => 'uniform',
                'minimum_quantity' => 150,
                'quantity_step' => 1,

                'maximum_quantity' => null,
                'total_selection_count' => $count,
                'draft_revision' => $draft->draft_revision,
            ], $draft);
            app(SavePackageMenuConfiguration::class)->handle($this->admin, $draft->fresh(), ['draft_revision' => $draft->fresh()->draft_revision, 'total_selection_count' => $count, 'food_item_ids' => $items->pluck('id')->all(), 'required_category_ids' => []]);
        }
        $b = $products['Package B'];
        $version = $b->versions()->sole();
        $version->refresh();
        $this->assertSame(4, $products['Package A']->versions()->sole()->total_selection_count);
        $this->assertSame(5, $version->total_selection_count);
        $this->assertSame(6, $products['Package C']->versions()->sole()->total_selection_count);
        $allowed = $items->filter(fn (Dish $dish): bool => in_array($dish->name, ['Fried Rice', 'Cola', 'Juice', 'Beef Caldereta', 'Chicken Adobo'], true))->pluck('id')->all();
        app(SavePackageMenuConfiguration::class)->handle($this->admin, $version, ['draft_revision' => $version->draft_revision, 'total_selection_count' => 5, 'food_item_ids' => $allowed, 'required_category_ids' => [$categories['Rice']->id, $categories['Drinks']->id]]);
        $version->refresh();
        $this->assertTrue((bool) DB::table('package_version_food_items')->where(['package_version_id' => $version->id, 'dish_id' => $items->first()->id])->value('is_fixed'));
        $this->assertTrue((bool) DB::table('package_version_food_items')->where(['package_version_id' => $version->id, 'dish_id' => $items->first()->id])->exists());
        $this->assertCount(5, app(PackageMenuConfiguration::class)->validateCustomerSelection($version->fresh(), $allowed));
        $published = app(PublishPackageVersion::class)->handle($this->admin, $version, $version->draft_revision);
        $this->assertSame('published', $published->status);
        $this->assertSame($published->id, $b->fresh()->current_version_id);
        $this->assertSame(5, $published->published_snapshot['menu']['total_selection_count']);
        $draft = app(ClonePackageDraft::class)->handle($this->admin, $b->fresh(), $published);
        $this->assertSame('draft', $draft->status);
        $this->assertSame($published->id, $draft->cloned_from_id);
        $this->assertSame('published', $published->fresh()->status);
    }

    public function test_impossible_menu_is_rejected_and_food_item_surcharges_are_not_modeled(): void
    {
        $category = DishCategory::create(['code' => 'rice', 'name' => 'Rice', 'active' => true]);
        $dish = Dish::create(['code' => 'fried_rice', 'name' => 'Fried Rice', 'category_id' => $category->id, 'active' => true]);
        $product = app(SaveProduct::class)->handle($this->admin, ['family_id' => $this->family->id, 'name' => 'Impossible', 'description' => null, 'active' => true, 'creation_token' => (string) str()->uuid()]);
        $version = $product->versions()->sole();
        $this->expectException(ValidationException::class);
        app(SavePackageMenuConfiguration::class)->handle($this->admin, $version, ['draft_revision' => 1, 'total_selection_count' => 2, 'food_item_ids' => [$dish->id], 'required_category_ids' => [$category->id]]);
    }
}
