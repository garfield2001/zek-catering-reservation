<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Dish;
use App\Models\DishCategory;
use App\Models\DishOffering;
use App\Models\OfferingOption;
use App\Models\Permission;
use App\Models\ProductFamily;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Catalog\CatalogAvailability;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Support\LegacyCollections;
use Tests\TestCase;

class MasterCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        LegacyCollections::seed();
    }

    private function user(string $role = 'admin'): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('code', $role)->sole());

        return $user;
    }

    private function signIn(User $user): static
    {
        return $this->actingAs($user)->withSession(['access_version' => $user->access_version]);
    }

    private function grant(User $user, string $permission, string $effect = 'allow'): void
    {
        $user->permissionOverrides()->syncWithoutDetaching([
            Permission::where('code', $permission)->sole()->id => ['effect' => $effect],
        ]);
        $user->unsetRelations();
    }

    private function category(string $code = 'mains', string $name = 'Main dishes'): DishCategory
    {
        return DishCategory::create(['code' => $code, 'name' => $name, 'sort_order' => 1, 'active' => true]);
    }

    private function categoryData(array $changes = []): array
    {
        return ['name' => 'Main dishes', 'sort_order' => 1, 'active' => true, ...$changes];
    }

    private function dishData(DishCategory $category, array $changes = []): array
    {
        return ['name' => 'Roast Chicken', 'description' => null,
            'category_id' => $category->id, 'active' => true, 'family_ids' => [], ...$changes];
    }

    private function offeringData(ProductFamily $family, array $changes = []): array
    {
        return ['family_id' => $family->id, 'name' => 'Classic',
            'description' => null, 'active' => true, ...$changes];
    }

    private function optionData(array $changes = []): array
    {
        return ['label' => 'Regular tray', 'unit' => 'tray',
            'quantity' => '1.000', 'serving_pax' => 10, 'serving_claim' => 'Serves approximately 10',
            'active' => true, ...$changes];
    }

    public function test_seed_has_exactly_five_product_families_and_conservative_defaults(): void
    {
        $this->assertSame(['main_catering', 'belly_lechon', 'food_pack', 'food_tray', 'pica_pica'],
            ProductFamily::orderBy('id')->pluck('code')->all());
        $this->assertSame(5, DB::table('family_order_defaults')->count());
        $main = ProductFamily::where('code', 'main_catering')->sole();
        $this->assertSame(150, $main->orderDefault->minimum_quantity);
        $this->assertTrue($main->orderDefault->enforce_minimum_floor);
        $this->assertSame(10, ProductFamily::where('code', 'food_pack')->sole()->orderDefault->minimum_quantity);
        $this->assertDatabaseHas('permissions', ['code' => 'catalog.view']);
        $this->assertDatabaseHas('permissions', ['code' => 'catalog.manage']);
    }

    public function test_admin_can_manage_categories_and_case_insensitive_names_are_rejected(): void
    {
        $admin = $this->user();
        $this->signIn($admin)->post(route('catalog.categories.store'), $this->categoryData())
            ->assertRedirect();
        $category = DishCategory::where('name', 'Main dishes')->sole();
        $this->assertStringStartsWith('category_', $category->code);
        $this->assertDatabaseHas('audit_events', ['action' => 'catalog.category_created',
            'subject_type' => 'dish_category', 'subject_id' => $category->id]);
        $this->post(route('catalog.categories.store'), $this->categoryData([
            'code' => 'other', 'name' => '  MAIN DISHES  ',
        ]))->assertSessionHasErrors('name');
        $this->put(route('catalog.categories.update', $category), $this->categoryData([
            'active' => false, 'sort_order' => 9,
        ]))->assertRedirect();
        $this->assertDatabaseHas('dish_categories', ['id' => $category->id, 'active' => false, 'sort_order' => 1]);
        $this->assertDatabaseHas('audit_events', ['action' => 'catalog.category_updated', 'subject_id' => $category->id]);
    }

    public function test_food_item_detail_exposes_a_clear_setup_readiness_summary(): void
    {
        $admin = $this->user();
        $category = $this->category('setup', 'Setup foods');
        $dish = Dish::create(['code' => 'setup_food', 'category_id' => $category->id, 'name' => 'Setup Food', 'active' => true]);

        $this->signIn($admin)->get(route('catalog.dishes.show', $dish))
            ->assertInertia(fn ($page) => $page->where('setup.hasCategory', true)
                ->where('setup.eligibleCollections', 0)
                ->where('setup.activeOfferings', 0)
                ->where('setup.activeServingOptions', 0));
    }

    public function test_canonical_dish_can_serve_multiple_families_and_eligibility_history_is_retained(): void
    {
        $admin = $this->user();
        $category = $this->category();
        $main = ProductFamily::where('code', 'main_catering')->sole();
        $tray = ProductFamily::where('code', 'food_tray')->sole();
        $this->signIn($admin)->post(route('catalog.dishes.store'), $this->dishData($category, [
            'family_ids' => [$main->id, $tray->id],
        ]))->assertRedirect();
        $dish = Dish::where('name', 'Roast Chicken')->sole();
        $this->assertSame(2, DB::table('dish_family_eligibilities')->where('dish_id', $dish->id)->count());
        $this->post(route('catalog.offerings.store', $dish), $this->offeringData($tray))->assertRedirect();
        $offering = DishOffering::where('dish_id', $dish->id)->sole();
        $this->put(route('catalog.dishes.update', $dish), $this->dishData($category, [
            'family_ids' => [$main->id],
        ]))->assertRedirect();
        $this->assertDatabaseHas('dish_family_eligibilities', [
            'dish_id' => $dish->id, 'family_id' => $tray->id, 'active' => false,
        ]);
        $this->assertDatabaseHas('dish_offerings', ['id' => $offering->id]);
        $this->post(route('catalog.offerings.store', $dish), $this->offeringData($tray, [
            'code' => 'premium',
        ]))->assertSessionHasErrors('family_id');
        $this->put(route('catalog.dishes.update', $dish), $this->dishData($category, [
            'family_ids' => [$main->id, $tray->id],
        ]))->assertRedirect();
        $this->assertSame(2, DB::table('dish_family_eligibilities')->where('dish_id', $dish->id)->count());
        $this->assertDatabaseHas('dish_family_eligibilities', [
            'dish_id' => $dish->id, 'family_id' => $tray->id, 'active' => true,
        ]);
    }

    public function test_dish_duplicate_name_is_category_scoped_and_availability_is_checked(): void
    {
        $this->signIn($this->user());
        $first = $this->category();
        $second = $this->category('sides', 'Sides');
        $this->post(route('catalog.dishes.store'), $this->dishData($first))->assertRedirect();
        $this->post(route('catalog.dishes.store'), $this->dishData($first, [
            'code' => 'roast_chicken_2', 'name' => 'ROAST CHICKEN',
        ]))->assertSessionHasErrors('name');
        $this->post(route('catalog.dishes.store'), $this->dishData($second, [
            'code' => 'roast_chicken_2',
        ]))->assertRedirect();
        $first->update(['active' => false]);
        $this->post(route('catalog.dishes.store'), $this->dishData($first, [
            'code' => 'another_dish', 'name' => 'Another dish',
        ]))->assertSessionHasErrors('category_id');
        $this->post(route('catalog.dishes.store'), $this->dishData($second, [
            'code' => 'bad_family', 'name' => 'Bad family', 'family_ids' => [999999],
        ]))->assertSessionHasErrors('family_ids.0');
    }

    public function test_offerings_and_options_are_family_specific_and_support_variable_sizes(): void
    {
        $this->signIn($this->user());
        $category = $this->category();
        $tray = ProductFamily::where('code', 'food_tray')->sole();
        $lechon = ProductFamily::where('code', 'belly_lechon')->sole();
        $this->post(route('catalog.dishes.store'), $this->dishData($category, [
            'family_ids' => [$tray->id, $lechon->id],
        ]))->assertRedirect();
        $dish = Dish::where('name', 'Roast Chicken')->sole();
        $this->post(route('catalog.offerings.store', $dish), $this->offeringData($tray))->assertRedirect();
        $this->post(route('catalog.offerings.store', $dish), $this->offeringData($tray, [
            'code' => 'spicy', 'name' => 'Spicy',
        ]))->assertRedirect();
        $this->post(route('catalog.offerings.store', $dish), $this->offeringData($lechon))->assertRedirect();
        $this->assertSame(3, DishOffering::where('dish_id', $dish->id)->count());
        $offering = DishOffering::where('dish_id', $dish->id)->where('family_id', $tray->id)->where('name', 'Classic')->sole();
        $this->post(route('catalog.options.store', [$dish, $offering]), $this->optionData())->assertRedirect();
        $this->post(route('catalog.options.store', [$dish, $offering]), $this->optionData([
            'option_code' => 'large', 'label' => 'Large tray', 'quantity' => '2.500',
            'serving_pax' => 20,
        ]))->assertRedirect();
        $this->assertDatabaseHas('offering_options', ['offering_id' => $offering->id,
            'label' => 'Large tray', 'quantity' => '2.500', 'serving_pax' => 20]);
        $lechonOffering = DishOffering::where('dish_id', $dish->id)->where('family_id', $lechon->id)->sole();
        $this->post(route('catalog.options.store', [$dish, $lechonOffering]), $this->optionData([
            'unit' => 'kg', 'serving_pax' => null, 'serving_claim' => null,
        ]))->assertRedirect();
        $this->assertDatabaseHas('offering_options', ['offering_id' => $lechonOffering->id,
            'unit' => 'kg', 'serving_pax' => null]);
        $this->assertDatabaseHas('audit_events', ['action' => 'catalog.option_created']);
    }

    public function test_invalid_options_and_ineligible_activation_are_rejected(): void
    {
        $this->signIn($this->user());
        $category = $this->category();
        $family = ProductFamily::where('code', 'food_tray')->sole();
        $dish = Dish::create(['code' => 'chicken', 'name' => 'Chicken', 'category_id' => $category->id, 'active' => true]);
        $this->post(route('catalog.offerings.store', $dish), $this->offeringData($family))
            ->assertSessionHasErrors('family_id');
        $dish->eligibleFamilies()->attach($family->id, ['active' => true]);
        $this->post(route('catalog.offerings.store', $dish), $this->offeringData($family))->assertRedirect();
        $offering = DishOffering::where('dish_id', $dish->id)->sole();
        foreach ([['unit' => 'bucket'], ['quantity' => '0'], ['quantity' => '1.1234'], ['serving_pax' => 0]] as $changes) {
            $this->post(route('catalog.options.store', [$dish, $offering]), $this->optionData($changes))
                ->assertSessionHasErrors();
        }
        $this->post(route('catalog.options.store', [$dish, $offering]), $this->optionData())->assertRedirect();
        $this->post(route('catalog.options.store', [$dish, $offering]), $this->optionData())
            ->assertRedirect();
        $this->assertSame(2, $offering->options()->count());
        $this->assertSame(2, $offering->options()->distinct('option_code')->count('option_code'));
        $dish->eligibleFamilies()->updateExistingPivot($family->id, ['active' => false]);
        $this->post(route('catalog.options.store', [$dish, $offering]), $this->optionData([
            'option_code' => 'large',
        ]))->assertSessionHasErrors('family_id');
    }

    public function test_staff_permissions_and_admin_only_family_defaults_are_enforced_server_side(): void
    {
        $staff = $this->user('staff');
        $category = $this->category();
        $family = ProductFamily::where('code', 'food_pack')->sole();
        $defaults = ['minimum_quantity' => 24, 'quantity_step' => 6,
            'maximum_quantity' => 120, 'enforce_minimum_floor' => true];
        $this->signIn($staff)->get(route('catalog.categories.index'))->assertForbidden();
        $this->post(route('catalog.categories.store'), $this->categoryData(['code' => 'other']))->assertForbidden();
        $this->put(route('catalog.families.defaults.update', $family), $defaults)->assertForbidden();
        $this->grant($staff, 'catalog.view');
        $this->get(route('catalog.categories.index'))->assertRedirect(route('catalog.workspace'));
        $this->get(route('catalog.workspace'))->assertOk();
        $this->get(route('catalog.dishes.index'))->assertRedirect(route('catalog.workspace'));
        $this->post(route('catalog.categories.store'), $this->categoryData(['code' => 'other']))->assertForbidden();
        $this->grant($staff, 'catalog.manage');
        $this->post(route('catalog.categories.store'), $this->categoryData(['code' => 'other']))->assertRedirect();
        $this->put(route('catalog.families.defaults.update', $family), $defaults)->assertForbidden();
        $this->grant($staff, 'catalog.manage', 'deny');
        $this->post(route('catalog.categories.store'), $this->categoryData(['code' => 'denied']))->assertForbidden();
        $this->signIn($this->user())->put(route('catalog.families.defaults.update', $family), $defaults)->assertRedirect();
        $this->assertDatabaseHas('family_order_defaults', ['family_id' => $family->id,
            'minimum_quantity' => 24, 'quantity_step' => 6, 'maximum_quantity' => 120]);
        $this->assertDatabaseHas('audit_events', ['action' => 'catalog.family_default_updated',
            'subject_type' => 'family_order_default', 'subject_id' => $family->id]);
        $this->assertDatabaseHas('dish_categories', ['id' => $category->id]);
    }

    public function test_nested_routes_and_database_constraints_protect_relations(): void
    {
        $this->signIn($this->user());
        $category = $this->category();
        $family = ProductFamily::where('code', 'food_tray')->sole();
        $dish = Dish::create(['code' => 'chicken', 'name' => 'Chicken', 'category_id' => $category->id, 'active' => true]);
        $other = Dish::create(['code' => 'beef', 'name' => 'Beef', 'category_id' => $category->id, 'active' => true]);
        $dish->eligibleFamilies()->attach($family->id, ['active' => true]);
        $offering = DishOffering::create(['dish_id' => $dish->id, 'family_id' => $family->id,
            'code' => 'classic', 'name' => 'Classic', 'active' => true]);
        $this->get(route('catalog.offerings.show', [$other, $offering]))->assertNotFound();
        try {
            DB::transaction(fn () => DB::table('dish_offerings')->insert([
                'dish_id' => $other->id, 'family_id' => $family->id,
                'code' => 'invalid', 'name' => 'Invalid', 'active' => true,
            ]));
            $this->fail('Expected composite foreign key violation.');
        } catch (QueryException) {
            // The nested transaction rolls back to its savepoint on PostgreSQL.
        }
        $this->assertDatabaseHas('dish_offerings', ['id' => $offering->id]);
    }

    public function test_audit_failure_rolls_back_catalog_mutation(): void
    {
        $this->withoutExceptionHandling();
        $this->signIn($this->user());
        $this->mock(AuditLogger::class)->shouldReceive('record')->once()->andThrow(new RuntimeException('audit unavailable'));
        try {
            $this->post(route('catalog.categories.store'), $this->categoryData());
            $this->fail('Expected audit failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('audit unavailable', $exception->getMessage());
        }
        $this->assertDatabaseMissing('dish_categories', ['code' => 'mains']);
        $this->assertSame(0, AuditEvent::where('action', '!=', 'catalog.primary_service_provisioned')->count());
    }

    public function test_malformed_catalog_fields_are_rejected_without_string_coercion(): void
    {
        $this->signIn($this->user());
        $this->post(route('catalog.categories.store'), $this->categoryData([
            'code' => ['mains'], 'name' => ['Main dishes'],
        ]))->assertSessionHasErrors(['code', 'name']);
        $this->assertDatabaseMissing('dish_categories', ['name' => 'Array']);

        $category = $this->category();
        $family = ProductFamily::where('code', 'food_tray')->sole();
        $this->post(route('catalog.dishes.store'), $this->dishData($category, [
            'code' => ['dish'], 'name' => ['Chicken'], 'description' => ['not text'],
            'category_id' => [$category->id],
        ]))->assertSessionHasErrors(['code', 'name', 'description', 'category_id']);
        $this->assertDatabaseMissing('dishes', ['name' => 'Array']);

        $dish = Dish::create(['code' => 'chicken', 'name' => 'Chicken', 'category_id' => $category->id, 'active' => true]);
        $dish->eligibleFamilies()->attach($family->id, ['active' => true]);
        $this->post(route('catalog.offerings.store', $dish), $this->offeringData($family, [
            'code' => ['classic'], 'name' => ['Classic'],
        ]))->assertSessionHasErrors(['code', 'name']);
        $offering = DishOffering::create(['dish_id' => $dish->id, 'family_id' => $family->id,
            'code' => 'classic', 'name' => 'Classic', 'active' => true]);
        $this->post(route('catalog.options.store', [$dish, $offering]), $this->optionData([
            'option_code' => ['regular'], 'label' => ['Regular tray'],
        ]))->assertSessionHasErrors(['option_code', 'label']);
        $this->assertDatabaseCount('offering_options', 0);
    }

    public function test_family_default_cannot_be_removed_even_by_direct_postgresql_delete(): void
    {
        $family = ProductFamily::where('code', 'food_pack')->sole();
        try {
            DB::transaction(function () use ($family): void {
                DB::table('family_order_defaults')->where('family_id', $family->id)->delete();
                DB::statement('SET CONSTRAINTS family_order_default_cannot_leave_gap IMMEDIATE');
            });
            $this->fail('Expected missing family default to be rejected.');
        } catch (QueryException) {
            // A nested PostgreSQL savepoint leaves the test transaction usable.
        }
        $this->assertDatabaseHas('family_order_defaults', ['family_id' => $family->id]);
    }

    public function test_exclusive_and_all_family_eligibility_preserves_canonical_identity(): void
    {
        $this->signIn($this->user());
        $category = $this->category();
        $pack = ProductFamily::where('code', 'food_pack')->sole();
        $allIds = ProductFamily::orderBy('id')->pluck('id')->all();
        $this->post(route('catalog.dishes.store'), $this->dishData($category, [
            'family_ids' => [$pack->id],
        ]))->assertRedirect();
        $dish = Dish::where('name', 'Roast Chicken')->sole();
        $this->assertSame([$pack->id], $dish->eligibleFamilies()->wherePivot('active', true)->pluck('product_families.id')->all());
        $this->put(route('catalog.dishes.update', $dish), $this->dishData($category, [
            'family_ids' => $allIds,
        ]))->assertRedirect();
        $this->assertSame(5, $dish->eligibleFamilies()->wherePivot('active', true)->count());
        $this->put(route('catalog.dishes.update', $dish), $this->dishData($category))->assertRedirect();
        $this->assertSame(0, $dish->eligibleFamilies()->wherePivot('active', true)->count());
        $this->assertSame(5, $dish->eligibleFamilies()->count());
        $this->assertSame(1, Dish::where('name', 'Roast Chicken')->count());
    }

    public function test_deactivation_retains_records_and_combined_availability_fails_closed(): void
    {
        $this->signIn($this->user());
        $category = $this->category();
        $family = ProductFamily::where('code', 'food_tray')->sole();
        $this->post(route('catalog.dishes.store'), $this->dishData($category, [
            'family_ids' => [$family->id],
        ]))->assertRedirect();
        $dish = Dish::where('name', 'Roast Chicken')->sole();
        $this->post(route('catalog.offerings.store', $dish), $this->offeringData($family))->assertRedirect();
        $offering = DishOffering::where('dish_id', $dish->id)->sole();
        $this->post(route('catalog.options.store', [$dish, $offering]), $this->optionData())->assertRedirect();
        $option = OfferingOption::where('offering_id', $offering->id)->sole();
        $availability = app(CatalogAvailability::class);
        $this->assertTrue($availability->optionAvailable($option));

        $this->put(route('catalog.options.update', [$dish, $offering, $option]), $this->optionData([
            'active' => false,
        ]))->assertRedirect();
        $this->assertFalse($availability->optionAvailable($option));
        $this->put(route('catalog.options.update', [$dish, $offering, $option]), $this->optionData())->assertRedirect();
        $this->put(route('catalog.offerings.update', [$dish, $offering]), $this->offeringData($family, [
            'active' => false,
        ]))->assertRedirect();
        $this->assertFalse($availability->offeringAvailable($offering));
        $this->assertFalse($availability->optionAvailable($option));
        $this->put(route('catalog.offerings.update', [$dish, $offering]), $this->offeringData($family))->assertRedirect();
        $this->put(route('catalog.dishes.update', $dish), $this->dishData($category, [
            'active' => false, 'family_ids' => [$family->id],
        ]))->assertRedirect();
        $this->assertFalse($availability->dishEligibleForFamily($dish, $family));
        $this->assertFalse($availability->optionAvailable($option));
        $this->assertDatabaseHas('dishes', ['id' => $dish->id, 'active' => false]);
        $this->assertDatabaseHas('dish_offerings', ['id' => $offering->id]);
        $this->assertDatabaseHas('offering_options', ['id' => $option->id]);

        $this->put(route('catalog.dishes.update', $dish), $this->dishData($category, [
            'family_ids' => [$family->id],
        ]))->assertRedirect();
        $category->update(['active' => false]);
        $this->assertFalse($availability->optionAvailable($option));
        $category->update(['active' => true]);
        $family->forceFill(['active' => false])->save();
        $this->assertFalse($availability->optionAvailable($option));
        $family->forceFill(['active' => true])->save();
        $dish->eligibleFamilies()->updateExistingPivot($family->id, ['active' => false]);
        $this->assertFalse($availability->optionAvailable($option));
        $dish->eligibleFamilies()->updateExistingPivot($family->id, ['active' => true]);
        $this->assertTrue($availability->optionAvailable($option));
    }

    public function test_postgresql_checks_uniques_and_restrictive_foreign_keys(): void
    {
        $category = $this->category();
        $family = ProductFamily::where('code', 'food_tray')->sole();
        $dish = Dish::create(['code' => 'chicken', 'name' => 'Chicken', 'category_id' => $category->id, 'active' => true]);
        $dish->eligibleFamilies()->attach($family->id, ['active' => true]);
        $offering = DishOffering::create(['dish_id' => $dish->id, 'family_id' => $family->id,
            'code' => 'classic', 'name' => 'Classic', 'active' => true]);

        $this->assertSqlRejected(fn () => DB::table('dish_categories')->insert([
            'code' => 'another', 'name' => '  MAIN DISHES  ',
        ]));
        $this->assertSqlRejected(fn () => DB::table('dishes')->insert([
            'code' => 'another', 'name' => 'CHICKEN', 'category_id' => $category->id,
        ]));
        $this->assertSqlRejected(fn () => DB::table('dish_family_eligibilities')->insert([
            'dish_id' => $dish->id, 'family_id' => $family->id,
        ]));
        foreach ([
            ['unit' => 'bucket', 'quantity' => '1.000', 'serving_pax' => null],
            ['unit' => 'tray', 'quantity' => '0.000', 'serving_pax' => null],
            ['unit' => 'tray', 'quantity' => '1.000', 'serving_pax' => 0],
        ] as $invalid) {
            $this->assertSqlRejected(fn () => DB::table('offering_options')->insert([
                'offering_id' => $offering->id, 'option_code' => 'invalid', 'label' => 'Invalid', ...$invalid,
            ]));
        }
        $this->assertSqlRejected(fn () => DB::table('dish_categories')->where('id', $category->id)->delete());
        $this->assertSqlRejected(fn () => DB::table('dish_family_eligibilities')->where('dish_id', $dish->id)
            ->where('family_id', $family->id)->delete());
        $this->assertDatabaseHas('dish_offerings', ['id' => $offering->id]);
    }

    private function assertSqlRejected(callable $operation): void
    {
        try {
            DB::transaction($operation);
            $this->fail('Expected PostgreSQL to reject the operation.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }
    }
}
