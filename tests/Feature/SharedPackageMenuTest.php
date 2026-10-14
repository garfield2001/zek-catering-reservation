<?php

namespace Tests\Feature;

use App\Actions\Catalog\CreateCollection;
use App\Actions\Catalog\EnsurePrimaryService;
use App\Actions\Package\ClonePackageDraft;
use App\Actions\Package\PublishPackageVersion;
use App\Actions\Package\SavePackageMenuConfiguration;
use App\Actions\Package\SavePackageVersion;
use App\Actions\Package\SaveProduct;
use App\Models\Dish;
use App\Models\DishCategory;
use App\Models\PackageVersion;
use App\Models\ProductFamily;
use App\Models\Role;
use App\Models\User;
use App\Services\Catalog\PackagePricePreview;
use App\Services\Package\PackageMenuConfiguration;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SharedPackageMenuTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::where('code', 'admin')->sole());
        $this->actingAs($this->admin)->withSession(['access_version' => $this->admin->access_version]);
    }

    private function service(string $behavior): ProductFamily
    {
        return app(CreateCollection::class)->handle($this->admin, ['kind' => 'addon', 'behavior_type' => $behavior, 'name' => 'Service '.ProductFamily::count(), 'active' => true]);
    }

    private function menu(ProductFamily $family): array
    {
        $product = app(SaveProduct::class)->handle($this->admin, ['family_id' => $family->id, 'name' => 'Package A', 'active' => true]);
        $category = DishCategory::create(['code' => 'foods_'.$product->id, 'name' => 'Foods '.$product->id, 'active' => true]);
        $foods = collect(range(1, 3))->map(function ($i) use ($family, $category) {
            $food = Dish::create(['code' => 'food_'.$category->id.'_'.$i, 'name' => 'Food '.$i, 'category_id' => $category->id, 'active' => true]);
            $food->eligibleFamilies()->attach($family->id, ['active' => true]);

            return $food;
        });
        $draft = $product->versions()->sole();
        $data = ['draft_revision' => 1, 'total_selection_count' => 2, 'food_item_ids' => $foods->pluck('id')->all(), 'required_category_ids' => [$category->id]];
        $this->put(route('packages.versions.package-menu.update', [$product, $draft]), $data)->assertRedirect(route($family->behavior_type === 'main_package' ? 'catering.show' : 'packages.show', $product));

        return [$product, $draft->fresh(), $foods, $data];
    }

    private function price(PackageVersion $draft, array $values = []): PackageVersion
    {
        return app(SavePackageVersion::class)->handle($this->admin, $draft->product, [
            'draft_revision' => $draft->draft_revision, 'base_price_pesos' => '450.00', 'selection_policy' => 'uniform',
            'minimum_quantity' => 1, 'quantity_step' => 1, 'maximum_quantity' => null, ...$values,
        ], $draft);
    }

    public function test_belly_style_menu_measurements_capacity_two_sets_and_immutable_revision(): void
    {
        [$product, $draft, $foods, $data] = $this->menu($this->service('package'));
        $data['draft_revision'] = $draft->draft_revision;
        $data['food_details'] = [$foods[0]->id => ['is_fixed' => true, 'included_quantity' => '3.000', 'included_unit' => 'kg']];
        app(SavePackageMenuConfiguration::class)->handle($this->admin, $draft, $data);
        $draft = $this->price($draft->fresh(), ['estimated_capacity' => 15]);
        $result = app(PackagePricePreview::class)->calculate($draft, 2, [['quantity' => 2, 'selections' => ['food_items' => $foods->take(2)->pluck('id')->all()]]]);
        $this->assertSame('90000', $result['total_minor']);
        $this->assertSame('3.000', $result['groups'][0]['food_items'][0]['quantity_per_unit']);
        $this->assertSame(2, $result['groups'][0]['food_items'][0]['purchased_units']);
        $published = app(PublishPackageVersion::class)->handle($this->admin, $draft, $draft->draft_revision);
        $snapshot = $published->fresh()->snapshot();
        $this->assertSame(15, $snapshot['version']['estimated_capacity']);
        $this->assertSame('kg', $snapshot['menu']['food_items'][0]['included_unit']);
        $revision = app(ClonePackageDraft::class)->handle($this->admin, $product, $published);
        $this->price($revision, ['base_price_pesos' => '500.00', 'minimum_quantity' => 3]);
        $this->assertSame($snapshot, $published->fresh()->snapshot());
        $this->assertSame($published->id, $product->fresh()->current_version_id);
        $this->assertSame('3.000', $revision->foodItems()->first()->pivot->included_quantity);
    }

    public function test_package_quantities_are_independent_of_service_and_copied_values(): void
    {
        $family = app(EnsurePrimaryService::class)->handle();
        [$a, $draft] = $this->menu($family);
        $draft = $this->price($draft, ['minimum_quantity' => 100, 'quantity_step' => 10, 'maximum_quantity' => 400]);
        $b = app(SaveProduct::class)->handle($this->admin, ['family_id' => $family->id, 'name' => 'Package B', 'active' => true]);
        $copy = $this->price($b->versions()->sole(), $draft->only('minimum_quantity', 'quantity_step', 'maximum_quantity'));
        $this->price($copy, ['minimum_quantity' => 1]);
        $family->orderDefault->update(['minimum_quantity' => 900, 'quantity_step' => 100, 'enforce_minimum_floor' => true]);
        $this->assertSame(100, $draft->fresh()->minimum_quantity);
        $this->assertSame(1, $copy->fresh()->minimum_quantity);
        $this->assertSame(10, $draft->fresh()->quantity_step);
        $this->assertSame(400, $draft->fresh()->maximum_quantity);
    }

    public function test_packed_meals_split_menus_paid_drinks_and_total_purchase_rules(): void
    {
        [$product, $draft, $foods, $data] = $this->menu($this->service('packed_meal'));
        $data['draft_revision'] = $draft->draft_revision;
        $data['food_details'] = [$foods[2]->id => ['price_adjustment_pesos' => '10.00', 'included_quantity' => '1', 'included_unit' => 'bottle']];
        app(SavePackageMenuConfiguration::class)->handle($this->admin, $draft, $data);
        $draft = $this->price($draft->fresh(), ['base_price_pesos' => '100.00', 'minimum_quantity' => 50, 'quantity_step' => 10, 'selection_policy' => 'split']);
        $groups = [
            ['quantity' => 20, 'selections' => ['food_items' => [$foods[0]->id, $foods[1]->id]]],
            ['quantity' => 30, 'selections' => ['food_items' => [$foods[0]->id, $foods[2]->id]]],
        ];
        $this->assertSame('530000', app(PackagePricePreview::class)->calculate($draft, 50, $groups)['total_minor']);
        $this->expectException(ValidationException::class);
        app(PackagePricePreview::class)->calculate($draft, 60, $groups);
    }

    public function test_shared_split_menus_merge_identical_choices_before_optional_subgroup_rules(): void
    {
        [, $draft, $foods] = $this->menu($this->service('packed_meal'));
        $draft = $this->price($draft, ['selection_policy' => 'split', 'minimum_quantity' => 30]);
        $draft->update(['subgroup_minimum' => 15, 'subgroup_step' => 5, 'subgroup_maximum' => 30]);
        $ids = $foods->take(2)->pluck('id')->all();
        $preview = app(PackagePricePreview::class)->calculate($draft, 30, [
            ['quantity' => 10, 'selections' => ['food_items' => $ids]],
            ['quantity' => 20, 'selections' => ['food_items' => array_reverse($ids)]],
        ]);
        $this->assertCount(1, $preview['groups']);
        $this->assertSame(30, $preview['groups'][0]['quantity']);
        $this->expectException(ValidationException::class);
        app(PackagePricePreview::class)->calculate($draft, 30, [
            ['quantity' => 10, 'selections' => ['food_items' => $ids]],
            ['quantity' => 20, 'selections' => ['food_items' => [$foods[0]->id, $foods[2]->id]]],
        ]);
    }

    public function test_fixed_bundle_derives_count_and_disallows_substitution(): void
    {
        [, $draft, $foods] = $this->menu($this->service('fixed_bundle'));
        $this->assertSame(3, $draft->total_selection_count);
        $this->assertSame(3, $draft->foodItems->where('pivot.is_fixed', true)->count());
        $this->assertCount(3, app(PackageMenuConfiguration::class)->validateCustomerSelection($draft, $foods->pluck('id')->all()));
        $this->expectException(ValidationException::class);
        app(PackageMenuConfiguration::class)->validateCustomerSelection($draft, $foods->take(2)->pluck('id')->all());
    }

    public function test_main_surcharges_measurements_and_malformed_http_quantities_are_rejected(): void
    {
        [$product, $draft, $foods, $data] = $this->menu(app(EnsurePrimaryService::class)->handle());
        $data['draft_revision'] = $draft->draft_revision;
        $data['food_details'] = [$foods[0]->id => ['included_quantity' => '3', 'included_unit' => 'kg']];
        $this->putJson(route('packages.versions.package-menu.update', [$product, $draft]), $data)->assertUnprocessable()->assertJsonValidationErrors('food_details');
        $this->putJson(route('packages.versions.update', [$product, $draft]), [
            'draft_revision' => $draft->draft_revision, 'base_price_pesos' => '200.00', 'selection_policy' => 'uniform',
            'minimum_quantity' => ['wrong'], 'quantity_step' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('minimum_quantity');
        $this->assertSame(0, DB::table('package_version_food_items')->whereNotNull('included_quantity')->count());
    }

    public function test_automatic_fixed_food_is_not_confused_with_explicit_addon_inclusion(): void
    {
        [$product, $draft, $foods, $data] = $this->menu($this->service('package'));
        $data['draft_revision'] = $draft->draft_revision;
        $data['total_selection_count'] = 1;
        $data['food_item_ids'] = [$foods[0]->id];
        app(SavePackageMenuConfiguration::class)->handle($this->admin, $draft, $data);
        $fixed = $draft->fresh()->foodItems()->sole()->pivot;
        $this->assertTrue((bool) $fixed->getAttribute('is_fixed'));
        $this->assertFalse((bool) $fixed->getAttribute('always_include'));
        $data['draft_revision'] = $draft->fresh()->draft_revision;
        $data['food_item_ids'] = $foods->pluck('id')->all();
        app(SavePackageMenuConfiguration::class)->handle($this->admin, $draft, $data);
        $this->assertSame(0, $draft->fresh()->foodItems->where('pivot.is_fixed', true)->count());
    }

    public function test_published_selection_rejects_a_disabled_frozen_category_after_master_reclassification(): void
    {
        [$product, $draft, $foods] = $this->menu($this->service('package'));
        $original = DishCategory::create(['code' => 'original', 'name' => 'Original category', 'active' => true]);
        $foods[0]->update(['category_id' => $original->id]);
        $draft = $this->price($draft);
        $published = app(PublishPackageVersion::class)->handle($this->admin, $draft, $draft->draft_revision);
        $foods[0]->update(['category_id' => $foods[1]->category_id]);
        $original->update(['active' => false]);
        $rules = app(PackageMenuConfiguration::class);
        $rules->validatePublishedAvailability($published);
        $this->assertCount(2, $rules->validateCustomerSelection($published, [$foods[1]->id, $foods[2]->id]));
        $this->expectException(ValidationException::class);
        $rules->validateCustomerSelection($published, [$foods[0]->id, $foods[1]->id]);
    }

    public function test_legacy_package_form_routes_return_to_the_canonical_workspace(): void
    {
        [$product] = $this->menu($this->service('package'));
        $this->get(route('packages.create', ['family_id' => $product->family_id]))->assertRedirect(route('services.show', $product->family_id));
        $this->get(route('packages.edit', $product))->assertOk()->assertInertia(fn ($page) => $page->component('admin/packages/editor'));
        $this->get(route('packages.create', ['scope' => 'primary']))->assertRedirect(route('catering.index'));
    }

    public function test_legacy_catalog_editors_use_the_same_authorized_workspace_dialogs(): void
    {
        [, , $foods] = $this->menu($this->service('package'));
        $food = $foods[0];
        $this->get(route('catalog.dishes.edit', $food))->assertRedirect(route('catalog.workspace', ['edit_food' => $food->id]));
        $this->get(route('catalog.categories.edit', $food->category_id))->assertRedirect(route('catalog.workspace', ['edit_category' => $food->category_id]));
        $this->get(route('catalog.workspace', ['edit_food' => $food->id]))->assertInertia(fn ($page) => $page->where('initialEditor.kind', 'food')->where('initialEditor.item.id', $food->id));
        $this->get(route('catalog.workspace', ['create' => 'category']))->assertInertia(fn ($page) => $page->where('initialEditor.kind', 'category')->where('initialEditor.item', null));
        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('code', 'staff')->sole());
        $this->actingAs($staff)->withSession(['access_version' => $staff->access_version])->get(route('catalog.workspace', ['edit_food' => $food->id]))->assertForbidden();
    }

    public function test_inline_catalog_reuses_authorization_and_returns_to_package(): void
    {
        [$product] = $this->menu(app(EnsurePrimaryService::class)->handle());
        $this->post(route('catalog.categories.store'), ['name' => 'Inline category', 'active' => true, 'return_product_id' => $product->id])->assertRedirect(route('catering.show', $product));
        $category = DishCategory::where('name', 'Inline category')->sole();
        $this->post(route('catalog.dishes.store'), ['name' => 'Inline Food', 'category_id' => $category->id, 'family_ids' => [$product->family_id], 'active' => true, 'return_product_id' => $product->id])->assertRedirect(route('catering.show', $product));
        $this->assertTrue(Dish::where('name', 'Inline Food')->sole()->eligibleFamilies()->whereKey($product->family_id)->exists());
        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('code', 'staff')->sole());
        $this->actingAs($staff)->withSession(['access_version' => $staff->access_version])->post(route('catalog.dishes.store'), [
            'name' => 'Forbidden', 'category_id' => $category->id, 'family_ids' => [$product->family_id], 'active' => true, 'return_product_id' => $product->id,
        ])->assertForbidden();
        $this->assertDatabaseMissing('dishes', ['name' => 'Forbidden']);
    }
}
