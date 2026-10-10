<?php

namespace Tests\Feature;

use App\Actions\Package\SaveSelectionGroup;
use App\Actions\Package\SaveVariantDraft;
use App\Models\AuditEvent;
use App\Models\Dish;
use App\Models\DishCategory;
use App\Models\DishOffering;
use App\Models\OfferingOption;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\Role;
use App\Models\User;
use App\Services\Catalog\CatalogAvailability;
use App\Services\Catalog\PackagePricePreview;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DynamicCollectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function actor(string $role = 'admin'): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('code', $role)->sole());

        return $user;
    }

    private function signIn(User $user): static
    {
        return $this->actingAs($user)->withSession(['access_version' => $user->access_version]);
    }

    private function createCollection(string $name, string $kind, string $behavior): ProductFamily
    {
        $this->post(route('catalog.families.store'), [
            'name' => $name, 'description' => null, 'kind' => $kind,
            'behavior_type' => $behavior, 'sort_order' => 0, 'active' => true,
        ])->assertRedirect();

        return ProductFamily::where('name', $name)->whereNull('archived_at')->sole();
    }

    public function test_fresh_seed_contains_only_system_configuration_and_empty_catalog_is_usable(): void
    {
        $this->assertSame(0, ProductFamily::count());
        $this->assertSame(0, Dish::count());
        $this->assertSame(0, Product::count());
        $this->assertDatabaseHas('permissions', ['code' => 'family.manage']);
        $this->signIn($this->actor())->get(route('dashboard'))->assertOk()
            ->assertInertia(fn ($page) => $page->where('setup.hasPrimary', false)->where('setup.collections', 0));
        $this->get(route('catalog.families.index'))->assertOk()
            ->assertInertia(fn ($page) => $page->where('hasPrimary', false)->has('families.data', 0));
    }

    public function test_activity_history_redacts_legacy_sensitive_fields_before_sending_them_to_the_browser(): void
    {
        $admin = $this->actor();
        $this->signIn($admin);
        AuditEvent::create([
            'actor_id' => $admin->id, 'actor_label' => $admin->email,
            'action' => 'legacy.changed', 'subject_type' => 'system', 'subject_id' => null,
            'before_data' => ['name' => 'Old', 'password' => 'old-secret', 'nested' => ['api_token' => 'old-token']],
            'after_data' => ['name' => 'New', 'nested' => ['remember_token' => 'new-secret']],
            'context' => ['source' => 'test'],
        ]);
        $this->get(route('audit.index'))->assertOk()->assertSee('New')
            ->assertDontSee('old-secret')->assertDontSee('old-token')->assertDontSee('new-secret');
    }

    public function test_admin_creates_one_primary_and_multiple_independent_addons_with_supported_behaviors(): void
    {
        $this->signIn($this->actor());
        $main = $this->createCollection('Catering Packages', 'primary', 'main_package');
        $dessert = $this->createCollection('Dessert Boxes', 'addon', 'packed_meal');
        $snack = $this->createCollection('Snack Boxes', 'addon', 'packed_meal');
        $direct = $this->createCollection('Tray Market', 'addon', 'direct_sale');
        $this->assertNotSame($dessert->id, $snack->id);
        $this->assertNotSame($dessert->code, $snack->code);
        $this->assertSame('packed_meal', $dessert->behavior_type);
        $this->assertSame('packed_meal', $snack->behavior_type);
        $this->assertFalse($main->is_addon);
        $this->assertTrue($direct->is_addon);
        $this->assertSame(150, $main->orderDefault->minimum_quantity);
        $this->assertSame(1, $dessert->orderDefault->minimum_quantity);
        $this->assertSame(4, AuditEvent::where('action', 'catalog.collection_created')->count());
        $this->assertSame(4, DB::table('family_order_defaults')->count());
        $this->post(route('catalog.families.store'), [
            'name' => 'Duplicate main', 'kind' => 'primary', 'behavior_type' => 'main_package',
            'sort_order' => 0, 'active' => true,
        ])->assertSessionHasErrors('kind');
        $this->post(route('catalog.families.archive', $main))->assertRedirect();
        $replacement = $this->createCollection('Catering Packages', 'primary', 'main_package');
        $this->assertNotEquals($main->id, $replacement->id);
    }

    public function test_role_behavior_and_single_primary_rules_hold_in_postgresql(): void
    {
        $this->signIn($this->actor());
        $main = $this->createCollection('Catering', 'primary', 'main_package');
        $addon = $this->createCollection('Desserts', 'addon', 'package');
        foreach ([
            [fn () => DB::table('product_families')->insert([
                'code' => 'another_main', 'name' => 'Another main', 'is_addon' => false,
                'behavior_type' => 'main_package', 'active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]), '23505'],
            [fn () => DB::table('product_families')->where('id', $addon->id)->update(['behavior_type' => 'packed_meal']), '23514'],
            [fn () => DB::table('product_families')->where('id', $addon->id)->update(['is_addon' => false]), '23514'],
            [fn () => DB::table('product_families')->where('id', $main->id)->update(['behavior_type' => 'arbitrary_script']), '23514'],
        ] as [$write, $state]) {
            DB::statement('SAVEPOINT collection_constraint_test');
            try {
                $write();
                $this->fail('PostgreSQL accepted an invalid collection.');
            } catch (QueryException $exception) {
                $this->assertSame($state, $exception->errorInfo[0]);
            } finally {
                DB::statement('ROLLBACK TO SAVEPOINT collection_constraint_test');
            }
        }
        $this->post(route('catalog.families.store'), [
            'name' => 'Custom engine', 'kind' => 'addon', 'behavior_type' => 'arbitrary_script',
            'sort_order' => 0, 'active' => true,
        ])->assertSessionHasErrors('behavior_type');
    }

    public function test_primary_collection_accepts_more_than_four_independent_variants(): void
    {
        $admin = $this->actor();
        $this->signIn($admin);
        $main = $this->createCollection('Catering', 'primary', 'main_package');
        $product = Product::create(['family_id' => $main->id, 'code' => 'catering',
            'name' => 'Catering', 'active' => true]);
        for ($number = 1; $number <= 5; $number++) {
            app(SaveVariantDraft::class)->handle($admin, $product, [
                'code' => "package_$number", 'name' => "Package $number", 'active' => true,
                'base_price_minor' => (string) ($number * 10000), 'selection_policy' => 'uniform',
                'minimum_override' => null, 'step_override' => null,
                'maximum_override_enabled' => false, 'maximum_override' => null,
            ]);
        }
        $this->assertSame(5, $product->variants()->count());
        $this->assertSame(5, DB::table('variant_versions')->where('family_id', $main->id)->count());
        $other = $this->createCollection('Package Add-ons', 'addon', 'package');
        DB::statement('SAVEPOINT product_reparent_test');
        try {
            DB::table('products')->where('id', $product->id)->update(['family_id' => $other->id]);
            $this->fail('PostgreSQL allowed a package product to change collections.');
        } catch (QueryException $exception) {
            $this->assertSame('23514', $exception->errorInfo[0]);
        } finally {
            DB::statement('ROLLBACK TO SAVEPOINT product_reparent_test');
        }
    }

    public function test_collection_scoped_eligibility_and_rename_preserve_packed_meal_pricing(): void
    {
        $admin = $this->actor();
        $this->signIn($admin);
        $first = $this->createCollection('Dessert Boxes', 'addon', 'packed_meal');
        $second = $this->createCollection('Snack Boxes', 'addon', 'packed_meal');
        $category = DishCategory::create(['code' => 'dessert', 'name' => 'Dessert', 'active' => true]);
        $dish = Dish::create(['code' => 'brownie', 'category_id' => $category->id, 'name' => 'Brownie', 'active' => true]);
        DB::table('dish_family_eligibilities')->insert(['dish_id' => $dish->id, 'family_id' => $first->id,
            'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->assertTrue(app(CatalogAvailability::class)->dishEligibleForFamily($dish, $first));
        $this->assertFalse(app(CatalogAvailability::class)->dishEligibleForFamily($dish, $second));
        $offering = DishOffering::create(['dish_id' => $dish->id, 'family_id' => $first->id,
            'code' => 'boxed', 'name' => 'Boxed', 'active' => true]);
        $option = OfferingOption::create(['offering_id' => $offering->id, 'option_code' => 'regular',
            'label' => 'Regular', 'unit' => 'pack', 'quantity' => '1.000', 'active' => true]);
        $product = Product::create(['family_id' => $first->id, 'code' => 'dessert_box',
            'name' => 'Dessert Box', 'active' => true]);
        $version = app(SaveVariantDraft::class)->handle($admin, $product, [
            'code' => 'standard', 'name' => 'Standard', 'active' => true,
            'base_price_minor' => '12500', 'selection_policy' => 'uniform',
            'minimum_override' => null, 'step_override' => null,
            'maximum_override_enabled' => false, 'maximum_override' => null,
        ]);
        app(SaveSelectionGroup::class)->handle($admin, $version, [
            'group_code' => 'dessert', 'name' => 'Dessert choice', 'mode' => 'select_one',
            'slot_kind' => 'item', 'minimum_count' => 1, 'maximum_count' => 1,
            'unique_dishes' => true, 'category_mode' => 'free', 'inclusion_count' => '1.000',
            'sort_order' => 0, 'options' => [['offering_option_id' => $option->id,
                'is_default' => true, 'price_adjustment_minor' => '250']],
            'category_rules' => [], 'dish_rules' => [],
        ]);
        $selection = [['quantity' => 3, 'selections' => ['dessert' => [$option->id]]]];
        $this->assertSame('38250', app(PackagePricePreview::class)->calculate($version->fresh(), 3, $selection)['total_minor']);
        $this->put(route('catalog.families.update', $first), [
            'name' => 'Sweet Packs', 'description' => '', 'sort_order' => 0, 'active' => true,
        ])->assertRedirect();
        $this->assertSame('packed_meal', $first->fresh()->behavior_type);
        $this->assertSame('38250', app(PackagePricePreview::class)->calculate($version->fresh(), 3, $selection)['total_minor']);
        $this->assertFalse(app(CatalogAvailability::class)->dishEligibleForFamily($dish, $second));
    }

    public function test_collection_search_and_archived_filter_are_server_paginated(): void
    {
        $this->signIn($this->actor());
        $first = $this->createCollection('Dessert Boxes', 'addon', 'package');
        $this->createCollection('Snack Boxes', 'addon', 'package');
        $this->get(route('catalog.families.index', ['q' => 'Dessert', 'sort' => 'name']))->assertOk()
            ->assertInertia(fn ($page) => $page->where('families.total', 1)->where('families.data.0.id', $first->id));
        $this->get(route('catalog.families.index', ['q' => '%']))->assertOk()
            ->assertInertia(fn ($page) => $page->where('families.total', 0));
        $this->post(route('catalog.families.archive', $first))->assertRedirect();
        $this->get(route('catalog.families.index'))->assertOk()
            ->assertInertia(fn ($page) => $page->where('families.total', 1));
        $this->get(route('catalog.families.index', ['status' => 'archived']))->assertOk()
            ->assertInertia(fn ($page) => $page->where('families.total', 1)->where('families.data.0.id', $first->id));
    }

    public function test_staff_needs_collection_permission_and_archive_keeps_history(): void
    {
        $admin = $this->actor();
        $staff = $this->actor('staff');
        $this->signIn($staff)->post(route('catalog.families.store'), [
            'name' => 'Denied', 'kind' => 'addon', 'behavior_type' => 'package',
            'sort_order' => 0, 'active' => true,
        ])->assertForbidden();
        $staff->permissionOverrides()->syncWithoutDetaching([
            Permission::where('code', 'family.manage')->sole()->id => ['effect' => 'allow'],
        ]);
        $staff->unsetRelations();
        $collection = $this->createCollection('Staff Collection', 'addon', 'package');
        $this->signIn($admin)->post(route('catalog.families.archive', $collection))->assertRedirect();
        $this->assertNotNull($collection->fresh()->archived_at);
        $this->assertFalse($collection->fresh()->active);
        $this->assertDatabaseHas('audit_events', ['action' => 'catalog.collection_archived', 'subject_id' => $collection->id]);
        $replacement = $this->createCollection('Staff Collection', 'addon', 'package');
        $this->assertNotEquals($collection->id, $replacement->id);
        $this->assertSame('Staff Collection', $replacement->name);
    }

    public function test_package_addon_needs_no_kilograms_and_direct_sale_rejects_package_variants(): void
    {
        $admin = $this->actor();
        $this->signIn($admin);
        $package = $this->createCollection('Dessert Packages', 'addon', 'package');
        $direct = $this->createCollection('Tray Market', 'addon', 'direct_sale');
        $category = DishCategory::create(['code' => 'sweets', 'name' => 'Sweets', 'active' => true]);
        $dish = Dish::create(['code' => 'cake', 'category_id' => $category->id, 'name' => 'Cake', 'active' => true]);
        DB::table('dish_family_eligibilities')->insert(['dish_id' => $dish->id, 'family_id' => $package->id,
            'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $offering = DishOffering::create(['dish_id' => $dish->id, 'family_id' => $package->id,
            'code' => 'boxed', 'name' => 'Boxed', 'active' => true]);
        $option = OfferingOption::create(['offering_id' => $offering->id, 'option_code' => 'small',
            'label' => 'Small', 'unit' => 'piece', 'quantity' => '1.000', 'active' => true]);
        $product = Product::create(['family_id' => $package->id, 'code' => 'celebration',
            'name' => 'Celebration', 'active' => true]);
        $version = app(SaveVariantDraft::class)->handle($admin, $product, [
            'code' => 'regular', 'name' => 'Regular', 'active' => true,
            'base_price_minor' => '20000', 'selection_policy' => 'uniform',
            'minimum_override' => null, 'step_override' => null,
            'maximum_override_enabled' => false, 'maximum_override' => null,
        ]);
        app(SaveSelectionGroup::class)->handle($admin, $version, [
            'group_code' => 'cake', 'name' => 'Cake inclusion', 'mode' => 'fixed',
            'slot_kind' => 'item', 'minimum_count' => 1, 'maximum_count' => 1,
            'unique_dishes' => true, 'category_mode' => 'free', 'inclusion_count' => '1.000',
            'sort_order' => 0, 'options' => [['offering_option_id' => $option->id,
                'is_default' => true, 'price_adjustment_minor' => '500']],
            'category_rules' => [], 'dish_rules' => [],
        ]);
        $this->assertSame('per_set', $version->price_basis);
        $this->assertSame('41000', app(PackagePricePreview::class)->calculate($version->fresh(), 2,
            [['quantity' => 2, 'selections' => ['cake' => [$option->id]]]])['total_minor']);
        $directProduct = Product::create(['family_id' => $direct->id, 'code' => 'direct_items',
            'name' => 'Direct items', 'active' => true]);
        DB::statement('SAVEPOINT direct_sale_variant_test');
        try {
            DB::table('product_variants')->insert(['product_id' => $directProduct->id,
                'code' => 'wrong', 'name' => 'Wrong', 'created_at' => now(), 'updated_at' => now()]);
            $this->fail('Direct-sale product accepted a package variant.');
        } catch (QueryException $exception) {
            $this->assertSame('23514', $exception->errorInfo[0]);
        } finally {
            DB::statement('ROLLBACK TO SAVEPOINT direct_sale_variant_test');
        }
    }

    public function test_fixed_bundle_cannot_accept_customer_substitutions_or_premiums(): void
    {
        $admin = $this->actor();
        $this->signIn($admin);
        $bundle = $this->createCollection('Party Snacks', 'addon', 'fixed_bundle');
        $category = DishCategory::create(['code' => 'snacks', 'name' => 'Snacks', 'active' => true]);
        $dish = Dish::create(['code' => 'crackers', 'category_id' => $category->id,
            'name' => 'Crackers', 'active' => true]);
        DB::table('dish_family_eligibilities')->insert(['dish_id' => $dish->id,
            'family_id' => $bundle->id, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $offering = DishOffering::create(['dish_id' => $dish->id, 'family_id' => $bundle->id,
            'code' => 'classic', 'name' => 'Classic', 'active' => true]);
        $option = OfferingOption::create(['offering_id' => $offering->id, 'option_code' => 'regular',
            'label' => 'Regular', 'unit' => 'piece', 'quantity' => '1.000', 'active' => true]);
        $product = Product::create(['family_id' => $bundle->id, 'code' => 'party',
            'name' => 'Party', 'active' => true]);
        $version = app(SaveVariantDraft::class)->handle($admin, $product, [
            'code' => 'basic', 'name' => 'Basic', 'active' => true,
            'base_price_minor' => '50000', 'selection_policy' => 'uniform',
            'minimum_override' => null, 'step_override' => null,
            'maximum_override_enabled' => false, 'maximum_override' => null,
        ]);
        $data = ['group_code' => 'snack', 'name' => 'Snack', 'mode' => 'fixed',
            'slot_kind' => 'item', 'minimum_count' => 1, 'maximum_count' => 1,
            'unique_dishes' => true, 'category_mode' => 'free', 'inclusion_count' => '2.000',
            'sort_order' => 0, 'options' => [['offering_option_id' => $option->id,
                'is_default' => true, 'price_adjustment_minor' => '0']],
            'category_rules' => [], 'dish_rules' => []];
        app(SaveSelectionGroup::class)->handle($admin, $version, $data);
        $this->assertSame('100000', app(PackagePricePreview::class)->calculate($version->fresh(), 2,
            [['quantity' => 2, 'selections' => []]])['total_minor']);
        foreach ([
            fn () => app(SaveSelectionGroup::class)->handle($admin, $version,
                [...$data, 'group_code' => 'custom', 'mode' => 'select_one']),
            fn () => app(SaveSelectionGroup::class)->handle($admin, $version,
                [...$data, 'group_code' => 'premium', 'options' => [[
                    'offering_option_id' => $option->id, 'is_default' => true, 'price_adjustment_minor' => '1',
                ]]]),
            fn () => app(PackagePricePreview::class)->calculate($version->fresh(), 2,
                [['quantity' => 2, 'selections' => ['snack' => [$option->id + 999]]]]),
        ] as $invalid) {
            try {
                $invalid();
                $this->fail('Fixed bundle accepted a forbidden change.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
    }
}
