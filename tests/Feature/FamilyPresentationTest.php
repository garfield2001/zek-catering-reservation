<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Permission;
use App\Models\ProductFamily;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\LegacyCollections;
use Tests\TestCase;

class FamilyPresentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        LegacyCollections::seed();
    }

    private function actor(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('code', $role)->sole());

        return $user;
    }

    private function signIn(User $user): static
    {
        return $this->actingAs($user)->withSession(['access_version' => $user->access_version]);
    }

    private function payload(ProductFamily $family, array $changes = []): array
    {
        return ['name' => $family->name, 'description' => $family->description,
            'sort_order' => $family->sort_order, 'active' => $family->active, ...$changes];
    }

    public function test_admin_can_rename_reorder_and_deactivate_without_changing_family_identity(): void
    {
        $admin = $this->actor('admin');
        $main = ProductFamily::where('code', 'main_catering')->sole();
        $pack = ProductFamily::where('code', 'food_pack')->sole();
        $this->signIn($admin)->put(route('catalog.families.update', $main),
            $this->payload($main, ['name' => 'Catering Packages', 'description' => 'Primary catering',
                'sort_order' => 90]))->assertRedirect();
        $this->put(route('catalog.families.update', $pack),
            $this->payload($pack, ['name' => 'Packed Meals', 'description' => 'Boxed meals',
                'sort_order' => 1, 'active' => false]))->assertRedirect();
        $this->post(route('catalog.families.move', $pack), ['direction' => 'up'])->assertRedirect();
        $this->post(route('catalog.families.move', $pack), ['direction' => 'up'])->assertRedirect();
        $this->assertDatabaseHas('product_families', ['id' => $main->id, 'code' => 'main_catering',
            'is_addon' => false, 'name' => 'Catering Packages', 'sort_order' => 20]);
        $this->assertDatabaseHas('product_families', ['id' => $pack->id, 'code' => 'food_pack',
            'is_addon' => true, 'name' => 'Packed Meals', 'sort_order' => 0, 'active' => false]);
        $this->assertSame('food_pack', ProductFamily::orderBy('sort_order')->first()->code);
        $this->assertSame(2, AuditEvent::where('action', 'catalog.family_presentation_updated')->count());
    }

    public function test_staff_permissions_and_input_cannot_change_internal_family_identity(): void
    {
        $staff = $this->actor('staff');
        $family = ProductFamily::where('code', 'food_pack')->sole();
        $path = route('catalog.families.update', $family);
        $this->signIn($staff)->put($path, $this->payload($family, ['name' => 'Packed Meals']))->assertForbidden();
        $staff->permissionOverrides()->syncWithoutDetaching([
            Permission::where('code', 'family.view')->sole()->id => ['effect' => 'allow'],
        ]);
        $staff->unsetRelations();
        $this->get(route('catalog.families.index'))->assertRedirect(route('services.index'));
        $this->get(route('services.index'))->assertOk();
        $this->put($path, $this->payload($family, ['name' => 'Packed Meals']))->assertForbidden();
        $staff->permissionOverrides()->syncWithoutDetaching([
            Permission::where('code', 'family.manage')->sole()->id => ['effect' => 'allow'],
        ]);
        $staff->unsetRelations();
        $this->put($path, [...$this->payload($family, ['name' => 'Packed Meals']),
            'code' => 'main_catering', 'is_addon' => false])->assertRedirect();
        $this->assertDatabaseHas('product_families', ['id' => $family->id, 'code' => 'food_pack',
            'is_addon' => true, 'name' => 'Packed Meals']);
        $this->put(route('catalog.families.defaults.update', $family), [
            'minimum_quantity' => 2, 'quantity_step' => 1,
            'maximum_quantity' => null, 'enforce_minimum_floor' => false,
        ])->assertForbidden();
    }

    public function test_postgresql_rejects_duplicate_display_names_and_identity_changes(): void
    {
        $family = ProductFamily::where('code', 'food_pack')->sole();
        $variantFamily = ProductFamily::where('code', 'main_catering')->sole();
        foreach ([
            [fn () => DB::table('product_families')->where('id', $family->id)->update([
                'name' => '  '.strtoupper($variantFamily->name).'  ',
            ]), '23505'],
            [fn () => DB::table('product_families')->where('id', $family->id)->update([
                'code' => 'main_catering',
            ]), '23514'],
            [fn () => DB::table('product_families')->where('id', $family->id)->update([
                'is_addon' => false,
            ]), '23514'],
            [fn () => DB::table('product_families')->where('id', $family->id)->update([
                'name' => '   ',
            ]), '23514'],
        ] as [$write, $state]) {
            DB::statement('SAVEPOINT family_integrity_test');
            try {
                $write();
                $this->fail('PostgreSQL accepted invalid family data.');
            } catch (QueryException $exception) {
                $this->assertSame($state, $exception->errorInfo[0]);
            } finally {
                DB::statement('ROLLBACK TO SAVEPOINT family_integrity_test');
            }
        }
    }

    public function test_display_name_validation_rejects_malformed_and_duplicate_input(): void
    {
        $admin = $this->actor('admin');
        $family = ProductFamily::where('code', 'food_pack')->sole();
        $path = route('catalog.families.update', $family);
        $this->signIn($admin)->from(route('catalog.families.index'))->put($path,
            $this->payload($family, ['name' => ['Packed Meals']]))->assertSessionHasErrors('name');
        $this->put($path, $this->payload($family, ['name' => '  CATERING PACKAGES  ']))
            ->assertSessionHasErrors('name');
        $this->assertSame('Food Pack Lunch', $family->fresh()->name);
    }

    public function test_primary_service_name_is_system_managed_but_availability_remains_editable(): void
    {
        $family = ProductFamily::where('is_addon', false)->sole();
        $name = $family->name;
        $this->signIn($this->actor('admin'))->put(route('catalog.families.update', $family),
            $this->payload($family, ['name' => 'Renamed Catering']))->assertSessionHasErrors('name');
        $this->assertSame($name, $family->fresh()->name);
        $this->put(route('catalog.families.update', $family), $this->payload($family, ['active' => false]))->assertSessionHasNoErrors();
        $this->assertFalse($family->fresh()->active);
    }
}
