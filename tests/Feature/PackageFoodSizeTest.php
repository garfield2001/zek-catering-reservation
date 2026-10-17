<?php

namespace Tests\Feature;

use App\Actions\Catalog\CreateCollection;
use App\Actions\Package\ClonePackageDraft;
use App\Actions\Package\PublishPackageVersion;
use App\Actions\Package\SaveCompletePackage;
use App\Actions\Package\SavePackageMenuConfiguration;
use App\Models\Dish;
use App\Models\DishCategory;
use App\Models\MeasurementUnit;
use App\Models\PackageVersion;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\Catalog\PackagePricePreview;
use App\Services\Package\PackageFoodSizes;
use App\Services\Package\PackageServingDefaults;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PackageFoodSizeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private array $input;

    private Dish $food;

    private MeasurementUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::where('code', 'admin')->sole());
        $this->actingAs($this->admin)->withSession(['access_version' => $this->admin->access_version]);
        $family = app(CreateCollection::class)->handle($this->admin, ['kind' => 'addon', 'name' => 'Celebration Sets', 'behavior_type' => 'package', 'active' => true]);
        $category = DishCategory::create(['name' => 'Roasts', 'code' => 'roasts', 'active' => true]);
        $this->food = Dish::create(['category_id' => $category->id, 'name' => 'Roasted Food', 'code' => 'roast', 'active' => true]);
        $this->unit = MeasurementUnit::create(['name' => 'Weight', 'abbreviation' => 'w', 'active' => true]);
        $this->input = ['family_id' => $family->id, 'name' => 'Celebration', 'active' => true, 'creation_token' => (string) Str::uuid(),
            'base_price_pesos' => '1000.00', 'price_basis' => 'per_set', 'selection_policy' => 'uniform', 'minimum_quantity' => 1, 'quantity_step' => 1,
            'total_selection_count' => 1, 'food_item_ids' => [$this->food->id], 'required_category_ids' => [],
            'food_details' => [$this->food->id => ['is_fixed' => true]],
            'food_sizes' => [$this->food->id => ['measurement_unit_id' => $this->unit->id, 'choices' => [
                ['quantity' => '3', 'extra_price_pesos' => '0', 'is_default' => true],
                ['quantity' => '4', 'extra_price_pesos' => '600', 'is_default' => false],
            ]]]];
    }

    private function save(): PackageVersion
    {
        return app(SaveCompletePackage::class)->handle($this->admin, $this->input)->versions()->sole();
    }

    public function test_package_default_changes_only_inherited_servings_and_never_purchase_quantity(): void
    {
        $side = Dish::create(['category_id' => $this->food->category_id, 'name' => 'Side Dish', 'code' => 'side', 'active' => true]);
        $tray = MeasurementUnit::create(['name' => 'Tray', 'abbreviation' => 'tray', 'active' => true]);
        $this->input['food_item_ids'][] = $side->id;
        $this->input['total_selection_count'] = 2;
        $this->input['serving_default'] = ['quantity' => '1', 'measurement_unit_id' => $tray->id, 'size_label' => 'Medium'];
        $version = $this->save();
        $preview = fn (PackageVersion $draft) => app(PackagePricePreview::class)->calculate($draft, 2, [['quantity' => 2, 'selections' => ['food_items' => [$side->id, $this->food->id]]]]);
        $before = collect($preview($version)['groups'][0]['food_items'])->keyBy('food_item_id');
        $this->assertSame('package_default', $before[$side->id]['serving_source']);
        $this->assertSame('1.000', $before[$side->id]['quantity_per_unit']);
        $this->assertSame('Medium', $before[$side->id]['size_label']);
        $this->assertSame('3.000', $before[$this->food->id]['quantity_per_unit']);
        $this->assertSame('custom', $before[$this->food->id]['serving_source']);
        $this->input['serving_default']['quantity'] = '2';
        app(SavePackageMenuConfiguration::class)->handle($this->admin, $version, [...$this->input, 'draft_revision' => $version->draft_revision]);
        $result = $preview($version->fresh());
        $after = collect($result['groups'][0]['food_items'])->keyBy('food_item_id');
        $this->assertSame('2.000', $after[$side->id]['quantity_per_unit']);
        foreach (['quantity_per_unit', 'unit', 'size_label', 'serving_source'] as $field) {
            $this->assertSame($before[$this->food->id][$field], $after[$this->food->id][$field]);
        }
        $this->assertSame(2, $after[$side->id]['purchased_units']);
        $this->assertSame('200000', $result['total_minor']);
    }

    public function test_named_sizes_freeze_and_clone_labels_and_package_defaults(): void
    {
        $this->input['serving_default'] = ['quantity' => '1', 'measurement_unit_id' => $this->unit->id, 'size_label' => 'Medium'];
        $this->input['food_sizes'][$this->food->id]['choices'] = [
            ['quantity' => '1', 'size_label' => 'Small', 'extra_price_pesos' => '0', 'is_default' => true],
            ['quantity' => '1', 'size_label' => 'Large', 'extra_price_pesos' => '200', 'is_default' => false],
        ];
        $version = $this->save();
        $sizes = app(PackageFoodSizes::class);
        $this->assertSame('Small', $sizes->input($version)[$this->food->id]['choices'][0]['size_label']);
        $choice = $version->sizeGroups()->sole()->choices()->where('is_default', false)->sole();
        $preview = app(PackagePricePreview::class)->calculate($version, 3, [['quantity' => 3, 'selections' => ['food_items' => [$this->food->id], 'food_sizes' => [$this->food->id => $choice->id]]]]);
        $this->assertSame('360000', $preview['total_minor']);
        $this->assertSame('Large', $preview['groups'][0]['food_items'][0]['size_label']);
        app(PublishPackageVersion::class)->handle($this->admin, $version, $version->draft_revision);
        $version = $version->fresh();
        $this->unit->update(['name' => 'Renamed', 'abbreviation' => 'renamed']);
        $this->assertSame('w', app(PackageServingDefaults::class)->effectiveDefault($version)['measurement_unit']['abbreviation']);
        $this->assertSame('Small', $sizes->resolve($version, [$this->food->id], [])[$this->food->id]['size_label']);
        $draft = app(ClonePackageDraft::class)->handle($this->admin, $version->product, $version);
        $this->assertSame('Medium', $draft->default_serving_size_label);
        $this->assertSame('Large', $sizes->input($draft)[$this->food->id]['choices'][1]['size_label']);
    }

    public function test_named_duplicates_and_double_charging_are_rejected_atomically(): void
    {
        $data = $this->input;
        $data['food_sizes'][$this->food->id]['choices'][0]['size_label'] = 'Medium';
        $data['food_sizes'][$this->food->id]['choices'][1]['size_label'] = ' medium ';
        $this->post(route('packages.configurations.store'), $data)->assertSessionHasErrors();
        $this->assertSame(0, Product::count());
        $data = $this->input;
        $data['food_details'][$this->food->id]['price_adjustment_pesos'] = '100';
        $this->post(route('packages.configurations.store'), $data)->assertSessionHasErrors('food_sizes');
        $this->assertSame(0, Product::count());
    }

    public function test_postgresql_rejects_partial_defaults_named_duplicates_and_published_default_changes(): void
    {
        $this->input['serving_default'] = ['quantity' => '1', 'measurement_unit_id' => $this->unit->id];
        $version = $this->save();
        $choices = $version->sizeGroups()->sole()->choices()->get();
        $choices[0]->update(['size_label' => 'Small']);
        foreach ([
            fn () => DB::table('package_versions')->where('id', $version->id)->update(['default_serving_unit_id' => null]),
            fn () => DB::table('package_versions')->where('id', $version->id)->update(['default_serving_quantity' => 'NaN']),
            fn () => DB::table('package_food_size_choices')->where('id', $choices[0]->id)->update(['quantity' => 'NaN']),
            fn () => $choices[1]->update(['size_label' => ' small ']),
        ] as $operation) {
            DB::beginTransaction();
            try {
                $operation();
                $this->fail('Expected PostgreSQL constraint rejection.');
            } catch (QueryException $exception) {
                $this->assertContains($exception->errorInfo[0], ['23514', '23505']);
            } finally {
                DB::rollBack();
            }
        }
        $version->refresh();
        app(PublishPackageVersion::class)->handle($this->admin, $version, $version->draft_revision);
        $this->expectException(QueryException::class);
        $version->fresh()->update(['default_serving_quantity' => '2']);
    }

    public function test_size_choice_counts_as_one_food_and_surcharge_multiplies_by_package_quantity(): void
    {
        $version = $this->save();
        $choice = $version->sizeGroups()->sole()->choices()->where('is_default', false)->sole();
        $preview = app(PackagePricePreview::class)->calculate($version, 2, [['quantity' => 2, 'selections' => ['food_items' => [$this->food->id], 'food_sizes' => [$this->food->id => $choice->id]]]]);
        $this->assertSame('320000', $preview['total_minor']);
        $this->assertCount(1, $preview['groups'][0]['food_items']);
        $default = app(PackagePricePreview::class)->calculate($version, 2, [['quantity' => 2, 'selections' => ['food_items' => [$this->food->id]]]]);
        $this->assertSame('200000', $default['total_minor']);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        DB::statement('SET CONSTRAINTS ALL DEFERRED');
    }

    public function test_publication_freezes_unit_labels_prices_and_cloning_copies_owned_choices(): void
    {
        $version = $this->save();
        app(PublishPackageVersion::class)->handle($this->admin, $version, $version->draft_revision);
        $snapshot = $version->fresh()->snapshot();
        $this->unit->update(['name' => 'Renamed Unit', 'abbreviation' => 'new', 'active' => false]);
        $this->assertSame($snapshot, $version->fresh()->snapshot());
        $resolved = app(PackageFoodSizes::class)->resolve($version->fresh(), [$this->food->id], []);
        $this->assertSame('w', $resolved[$this->food->id]['measurement_unit']['abbreviation']);
        $draft = app(ClonePackageDraft::class)->handle($this->admin, $version->product, $version->fresh());
        $this->assertSame(2, $draft->sizeGroups()->sole()->choices()->count());
        $this->assertSame($version->id, $version->product->fresh()->current_version_id);
        $this->expectException(ValidationException::class);
        app(PublishPackageVersion::class)->handle($this->admin, $draft, $draft->draft_revision);
    }

    public function test_invalid_sizes_defaults_and_units_roll_back_the_entire_package(): void
    {
        foreach ([
            [['quantity' => '3', 'extra_price_pesos' => '0', 'is_default' => false]],
            [['quantity' => '3', 'extra_price_pesos' => '1', 'is_default' => true]],
            [['quantity' => '3', 'extra_price_pesos' => '0', 'is_default' => true], ['quantity' => '3.000', 'extra_price_pesos' => '1', 'is_default' => false]],
        ] as $choices) {
            $data = $this->input;
            $data['food_sizes'][$this->food->id]['choices'] = $choices;
            $this->post(route('packages.configurations.store'), $data)->assertSessionHasErrors();
            $this->assertSame(0, Product::count());
        }
        $this->unit->update(['active' => false]);
        $this->post(route('packages.configurations.store'), $this->input)->assertSessionHasErrors('food_sizes.'.$this->food->id.'.measurement_unit_id');
        $this->assertSame(0, Product::count());
    }

    public function test_postgresql_rejects_published_child_mutation_and_referenced_unit_deletion(): void
    {
        $version = $this->save();
        app(PublishPackageVersion::class)->handle($this->admin, $version, $version->draft_revision);
        $choice = $version->sizeGroups()->sole()->choices()->firstOrFail();
        foreach ([fn () => $choice->update(['extra_price_minor' => 500]), fn () => $this->unit->delete()] as $operation) {
            DB::beginTransaction();
            try {
                $operation();
                $this->fail('Expected PostgreSQL integrity protection.');
            } catch (QueryException $exception) {
                $this->assertContains($exception->errorInfo[0], ['23514', '23503', '23001']);
            } finally {
                DB::rollBack();
            }
        }
    }

    public function test_unchanged_browser_size_values_do_not_require_pricing_permission(): void
    {
        $version = $this->save();
        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('code', 'staff')->sole());
        $staff->permissionOverrides()->attach(Permission::where('code', 'package.manage')->sole(), ['effect' => 'allow']);
        $data = $this->input;
        $data['food_sizes'][$this->food->id]['measurement_unit_id'] = (string) $this->unit->id;
        $data['food_sizes'][$this->food->id]['choices'][0]['quantity'] = '3.000';
        $data['food_sizes'][$this->food->id]['choices'][0]['extra_price_pesos'] = '0.00';
        app(SavePackageMenuConfiguration::class)->handle($staff, $version, [...$data, 'draft_revision' => $version->draft_revision]);
        $this->assertSame($version->draft_revision + 1, $version->fresh()->draft_revision);
        $data['food_sizes'][$this->food->id]['choices'][1]['extra_price_pesos'] = '700';
        $this->expectException(AuthorizationException::class);
        app(SavePackageMenuConfiguration::class)->handle($staff, $version->fresh(), [...$data, 'draft_revision' => $version->fresh()->draft_revision]);
    }

    public function test_malformed_choice_identifiers_are_validation_errors_not_coerced_values(): void
    {
        $version = $this->save();
        foreach ([[], ['id' => 1], true, 1.5, '1x'] as $value) {
            try {
                app(PackageFoodSizes::class)->resolve($version, [$this->food->id], [$this->food->id => $value]);
                $this->fail('Malformed size selection was accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('food_sizes', $exception->errors());
            }
        }
    }
}
