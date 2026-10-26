<?php

namespace Tests\Feature;

use App\Actions\Catalog\CreateCollection;
use App\Actions\Catalog\SaveMeasurementUnit;
use App\Models\AuditEvent;
use App\Models\MeasurementUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MeasurementUnitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function account(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('code', $role)->sole());
        $this->actingAs($user)->withSession(['access_version' => $user->access_version]);

        return $user;
    }

    public function test_units_have_configurable_labels_no_mandatory_seed_and_audited_status(): void
    {
        $this->account('admin');
        $this->assertSame(0, MeasurementUnit::count());
        $this->post(route('catalog.units.store'), ['name' => ' Serving Bowls ', 'abbreviation' => ' bowl ', 'active' => true])->assertSessionHasNoErrors()->assertRedirect(route('catalog.units.index'));
        $unit = MeasurementUnit::sole();
        $this->assertSame('Serving Bowls', $unit->name);
        $this->assertSame('bowl', $unit->abbreviation);
        $this->put(route('catalog.units.update', $unit), ['name' => 'Bowls', 'abbreviation' => 'bwl', 'active' => false])->assertSessionHasNoErrors();
        $this->assertFalse($unit->fresh()->active);
        $this->assertSame(2, AuditEvent::where('action', 'like', 'catalog.measurement_unit_%')->count());
        $this->get(route('catalog.units.index'))->assertRedirect(route('catalog.workspace'));
        $this->getJson(route('catalog.units.index'))
            ->assertOk()
            ->assertJsonPath('units.0.name', 'Bowls')
            ->assertJsonPath('units.0.usage_count', 0)
            ->assertJsonPath('canManage', true);
        $this->delete(route('catalog.units.destroy', $unit))->assertSessionHasNoErrors();
        $this->assertSame(0, MeasurementUnit::count());
    }

    public function test_duplicate_case_whitespace_and_malformed_labels_are_rejected(): void
    {
        $this->account('admin');
        $this->post(route('catalog.units.store'), ['name' => 'Bowls', 'abbreviation' => 'bwl', 'active' => true])->assertSessionHasNoErrors();
        $this->post(route('catalog.units.store'), ['name' => ' BOWLS ', 'abbreviation' => ' BWL ', 'active' => true])->assertSessionHasErrors(['name', 'abbreviation']);
        $this->post(route('catalog.units.store'), ['name' => ['bad'], 'abbreviation' => ['bad'], 'active' => true])->assertSessionHasErrors(['name', 'abbreviation']);
        $this->assertSame(1, MeasurementUnit::count());
        DB::beginTransaction();
        try {
            MeasurementUnit::create(['name' => 'bowls', 'abbreviation' => 'different', 'active' => true]);
            $this->fail('PostgreSQL must reject duplicate normalized names.');
        } catch (QueryException $exception) {
            $this->assertSame('23505', $exception->errorInfo[0]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_staff_requires_catalog_permissions_and_forged_writes_are_rejected(): void
    {
        $staff = $this->account('staff');
        $this->getJson(route('catalog.units.index'))->assertForbidden();
        $this->post(route('catalog.units.store'), ['name' => 'Bowls', 'abbreviation' => 'bwl', 'active' => true])->assertForbidden();
        $staff->permissionOverrides()->attach(Permission::where('code', 'catalog.view')->sole(), ['effect' => 'allow']);
        $this->actingAs($staff->fresh());
        $this->get(route('catalog.units.index'))->assertRedirect(route('catalog.workspace'));
        $this->getJson(route('catalog.units.index'))
            ->assertOk()
            ->assertJsonPath('canManage', false);
        $this->post(route('catalog.units.store'), ['name' => 'Bowls', 'abbreviation' => 'bwl', 'active' => true])->assertForbidden();
        $this->assertSame(0, MeasurementUnit::count());
    }

    public function test_failed_audit_rolls_back_unit_creation(): void
    {
        $admin = $this->account('admin');
        $this->mock(AuditLogger::class)->shouldReceive('record')->andThrow(new \RuntimeException('Audit unavailable.'));
        try {
            app(SaveMeasurementUnit::class)->handle($admin, ['name' => 'Bowls', 'abbreviation' => 'bwl', 'active' => true]);
            $this->fail('Expected audit failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Audit unavailable.', $exception->getMessage());
        }
        $this->assertSame(0, MeasurementUnit::count());
    }

    public function test_inline_unit_creation_returns_persisted_unit_without_redirect_and_still_authorizes(): void
    {
        $this->account('admin');
        $response = $this->postJson(route('catalog.units.store'), ['name' => 'Trays', 'abbreviation' => 'tray', 'active' => true]);
        $response->assertCreated()->assertJsonPath('unit.name', 'Trays');
        $this->assertSame(MeasurementUnit::sole()->id, $response->json('unit.id'));
        $this->assertSame(1, AuditEvent::where('action', 'catalog.measurement_unit_created')->count());
        $this->postJson(route('catalog.units.store'), ['name' => 'trays', 'abbreviation' => 'tray', 'active' => true])->assertUnprocessable();
        $this->account('staff');
        $this->postJson(route('catalog.units.store'), ['name' => 'Bowls', 'abbreviation' => 'bowl', 'active' => true])->assertForbidden();
        $this->assertSame(1, MeasurementUnit::count());
    }

    public function test_staff_cannot_delete_a_measurement_unit_through_a_forged_request(): void
    {
        $this->account('admin');
        $unit = MeasurementUnit::create(['name' => 'Unused bowl', 'abbreviation' => 'bowl', 'active' => true]);
        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('code', 'staff')->sole());
        $staff->permissionOverrides()->attach(Permission::where('code', 'catalog.view')->sole(), ['effect' => 'allow']);

        $this->actingAs($staff)->withSession(['access_version' => $staff->access_version])
            ->delete(route('catalog.units.destroy', $unit))
            ->assertForbidden();

        $this->assertModelExists($unit);
    }

    public function test_used_units_cannot_be_renamed_or_deleted_but_can_be_deactivated_and_reactivated(): void
    {
        $admin = $this->account('admin');
        $unit = MeasurementUnit::create(['name' => 'Tray', 'abbreviation' => 'tray', 'active' => true]);
        $family = app(CreateCollection::class)->handle($admin, [
            'kind' => 'addon', 'name' => 'Unit service', 'behavior_type' => 'package', 'active' => true,
            'package_configuration' => ['format' => 'separate_servings', 'quantity' => 1, 'measurement_unit_id' => $unit->id],
        ]);
        $this->put(route('catalog.units.update', $unit), ['name' => 'Trays', 'abbreviation' => 'trays', 'active' => true])
            ->assertSessionHasErrors('name');
        $this->delete(route('catalog.units.destroy', $unit))->assertSessionHasErrors('unit');
        $this->put(route('catalog.units.update', $unit), ['name' => 'Tray', 'abbreviation' => 'tray', 'active' => false])
            ->assertSessionHasNoErrors();
        $this->assertFalse($unit->fresh()->active);
        $this->put(route('catalog.units.update', $unit), ['name' => 'Tray', 'abbreviation' => 'tray', 'active' => true])
            ->assertSessionHasNoErrors();
        $this->assertTrue($unit->fresh()->active);
        $this->assertSame($family->id, $family->fresh()->id);
    }

    public function test_json_management_writes_return_current_units_without_bypassing_lifecycle_protection(): void
    {
        $admin = $this->account('admin');
        $unit = MeasurementUnit::create(['name' => 'Box', 'abbreviation' => 'box', 'active' => true]);

        $this->putJson(route('catalog.units.update', $unit), [
            'name' => 'Boxes', 'abbreviation' => 'box', 'active' => false,
        ])->assertOk()->assertJsonPath('unit.name', 'Boxes')->assertJsonPath('unit.active', false);

        $this->deleteJson(route('catalog.units.destroy', $unit))
            ->assertNoContent();
        $this->assertModelMissing($unit);

        $used = MeasurementUnit::create(['name' => 'Tray', 'abbreviation' => 'tray', 'active' => true]);
        app(CreateCollection::class)->handle($admin, [
            'kind' => 'addon', 'name' => 'Used unit service', 'behavior_type' => 'package', 'active' => true,
            'package_configuration' => ['format' => 'separate_servings', 'quantity' => 1, 'measurement_unit_id' => $used->id],
        ]);

        $this->getJson(route('catalog.units.index'))
            ->assertOk()
            ->assertJsonPath('units.0.usage_count', 1);
        $this->deleteJson(route('catalog.units.destroy', $used))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('unit');
    }
}
