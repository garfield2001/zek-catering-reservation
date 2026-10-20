<?php

namespace Tests\Feature;

use App\Actions\Catalog\CreateCollection;
use App\Actions\Catalog\UpdateFamilyPresentation;
use App\Actions\Package\ClonePackageDraft;
use App\Actions\Package\SaveCompletePackage;
use App\Models\Dish;
use App\Models\DishCategory;
use App\Models\MeasurementUnit;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\Role;
use App\Models\User;
use App\Services\Catalog\PackagePricePreview;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ServicePackageInheritanceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private ProductFamily $family;

    private MeasurementUnit $tray;

    private array $foods;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::where('code', 'admin')->sole());
        $this->actingAs($this->admin)->withSession(['access_version' => $this->admin->access_version]);
        $this->tray = MeasurementUnit::create(['name' => 'Tray', 'abbreviation' => 'tray', 'active' => true]);
        $this->family = app(CreateCollection::class)->handle($this->admin, ['name' => 'Configured food service', 'kind' => 'addon', 'behavior_type' => 'package', 'active' => true,
            'package_configuration' => ['format' => 'separate_servings', 'quantity' => 1, 'measurement_unit_id' => $this->tray->id]]);
        $category = DishCategory::create(['name' => 'Foods', 'code' => 'foods', 'active' => true]);
        $this->foods = [];
        for ($i = 1; $i <= 6; $i++) {
            $this->foods[] = Dish::create(['name' => "Food {$i}", 'code' => "food_{$i}", 'category_id' => $category->id, 'active' => true])->id;
        }
    }

    private function data(int $count = 4): array
    {
        return ['family_id' => $this->family->id, 'name' => "Package {$count}", 'active' => true, 'creation_token' => (string) Str::uuid(),
            'base_price_pesos' => '1000', 'price_basis' => 'per_set', 'selection_policy' => 'uniform', 'minimum_quantity' => 1, 'quantity_step' => 1,
            'total_selection_count' => $count, 'food_item_ids' => array_slice($this->foods, 0, $count), 'required_category_ids' => [],
            'food_details' => array_fill_keys(array_slice($this->foods, 0, $count), ['is_fixed' => true])];
    }

    public function test_new_packages_inherit_service_defaults_with_actual_distinct_food_counts(): void
    {
        foreach ([4, 6] as $count) {
            $this->postJson(route('packages.configurations.store'), $this->data($count))->assertSuccessful();
            $product = Product::where('name', "Package {$count}")->sole();
            $this->assertTrue($product->service_format_owned);
            $version = $product->versions()->sole();
            $this->assertSame('separate_servings', $version->package_format);
            $this->assertSame($this->tray->id, $version->presentation_unit_id);
            $preview = app(PackagePricePreview::class)->calculate($version, 1, [['quantity' => 1, 'selections' => ['food_items' => array_slice($this->foods, 0, $count)]]]);
            $this->assertCount($count, $preview['groups'][0]['food_items']);
            foreach ($preview['groups'][0]['food_items'] as $food) {
                $this->assertSame(1, $food['presentation']['quantity']);
            }
        }
    }

    public function test_forged_package_format_and_default_are_rejected_atomically(): void
    {
        foreach (['format' => 'combined_contents', 'quantity' => 2, 'measurement_unit_id' => null] as $key => $value) {
            $presentation = ['format' => 'separate_servings', 'quantity' => 1, 'measurement_unit_id' => $this->tray->id, $key => $value];
            $this->postJson(route('packages.configurations.store'), [...$this->data(), 'service_format_owned' => false, 'presentation' => $presentation])->assertUnprocessable()->assertJsonValidationErrors('presentation');
            $this->assertSame(0, Product::count());
        }
        $product = app(SaveCompletePackage::class)->handle($this->admin, $this->data());
        $this->expectException(QueryException::class);
        DB::transaction(fn () => $product->versions()->sole()->forceFill(['package_format' => 'combined_contents'])->save());
    }

    public function test_weight_upgrades_do_not_change_inherited_container_counts_or_double_charge(): void
    {
        $kg = MeasurementUnit::create(['name' => 'Kilogram', 'abbreviation' => 'kg', 'active' => true]);
        $product = app(SaveCompletePackage::class)->handle($this->admin, [...$this->data(),
            'food_sizes' => [$this->foods[0] => ['measurement_unit_id' => $kg->id, 'choices' => [
                ['quantity' => '3', 'extra_price_pesos' => '0', 'is_default' => true],
                ['quantity' => '4', 'extra_price_pesos' => '600', 'is_default' => false],
                ['quantity' => '5', 'extra_price_pesos' => '1200', 'is_default' => false],
            ]]],
        ]);
        $version = $product->versions()->sole();
        $choice = $version->sizeGroups()->sole()->choices()->where('quantity', 5)->sole();
        $preview = app(PackagePricePreview::class)->calculate($version, 2, [['quantity' => 2,
            'selections' => ['food_items' => array_slice($this->foods, 0, 4), 'food_sizes' => [$this->foods[0] => $choice->id]]]]);
        $this->assertSame('440000', $preview['total_minor']);
        $this->assertCount(4, $preview['groups'][0]['food_items']);
        foreach ($preview['groups'][0]['food_items'] as $food) {
            $this->assertSame(1, $food['presentation']['quantity']);
        }
        $roast = collect($preview['groups'][0]['food_items'])->firstWhere('food_item_id', $this->foods[0]);
        $this->assertSame('5.000', $roast['quantity_per_unit']);
    }

    public function test_new_service_defaults_do_not_change_existing_publications_or_clones(): void
    {
        $product = app(SaveCompletePackage::class)->handle($this->admin, [...$this->data(), 'save_action' => 'publish', 'confirm_publication' => true]);
        $published = $product->currentVersion;
        $snapshot = $published->snapshot();
        app(UpdateFamilyPresentation::class)->handle($this->admin, $this->family, ['name' => 'Renamed service', 'active' => true,
            'package_configuration' => ['format' => 'separate_servings', 'quantity' => 2, 'measurement_unit_id' => $this->tray->id]]);
        $clone = app(ClonePackageDraft::class)->handle($this->admin, $product, $published);
        $this->assertSame(1, $clone->presentation_quantity);
        $next = app(SaveCompletePackage::class)->handle($this->admin, $this->data(6));
        $this->assertSame(2, $next->versions()->sole()->presentation_quantity);
        $this->assertSame($snapshot, $published->fresh()->snapshot());
    }

    public function test_combined_named_containers_repeat_complete_units_and_unconfigured_services_fail_closed(): void
    {
        foreach (['Meal Pack', 'Meal Box'] as $label) {
            $unit = MeasurementUnit::create(['name' => $label, 'abbreviation' => Str::slug($label), 'active' => true]);
            $family = app(CreateCollection::class)->handle($this->admin, ['name' => $label.' service', 'kind' => 'addon', 'behavior_type' => 'package', 'active' => true,
                'package_configuration' => ['format' => 'combined_contents', 'quantity' => 1, 'measurement_unit_id' => $unit->id]]);
            $product = app(SaveCompletePackage::class)->handle($this->admin, [...$this->data(), 'family_id' => $family->id]);
            $preview = app(PackagePricePreview::class)->calculate($product->versions()->sole(), 10, [['quantity' => 10, 'selections' => ['food_items' => array_slice($this->foods, 0, 4)]]]);
            $this->assertSame(10, $preview['quantity']);
            $this->assertSame($label, $preview['presentation']['default']['measurement_unit']['name']);
            $this->assertSame('1000000', $preview['total_minor']);
            foreach ($preview['groups'][0]['food_items'] as $food) {
                $this->assertNull($food['presentation']);
            }
        }
        $legacy = app(CreateCollection::class)->handle($this->admin, ['name' => 'Unconfigured legacy', 'kind' => 'addon', 'behavior_type' => 'package', 'active' => true]);
        $this->postJson(route('packages.configurations.store'), [...$this->data(), 'family_id' => $legacy->id])->assertUnprocessable()->assertJsonValidationErrors('family_id');
    }
}
