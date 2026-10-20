<?php

namespace Tests\Feature;

use App\Actions\Catalog\CreateCollection;
use App\Actions\Catalog\UpdateFamilyPresentation;
use App\Actions\Package\ClonePackageDraft;
use App\Actions\Package\SaveCompletePackage;
use App\Actions\Package\SaveProduct;
use App\Models\AuditEvent;
use App\Models\Dish;
use App\Models\DishCategory;
use App\Models\MeasurementUnit;
use App\Models\ProductFamily;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ServicePackageConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private MeasurementUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::where('code', 'admin')->sole());
        $this->actingAs($this->admin)->withSession(['access_version' => $this->admin->access_version]);
        $this->unit = MeasurementUnit::create(['name' => 'Meal Box', 'abbreviation' => 'box', 'active' => true]);
    }

    private function family(string $behavior = 'package'): ProductFamily
    {
        return app(CreateCollection::class)->handle($this->admin, ['name' => 'Configurable service', 'kind' => 'addon', 'behavior_type' => $behavior, 'active' => true]);
    }

    private function settings(string $format = 'separate_servings', int $quantity = 1): array
    {
        return ['format' => $format, 'quantity' => $quantity, 'measurement_unit_id' => $this->unit->id];
    }

    private function update(ProductFamily $family, mixed $settings): TestResponse
    {
        return $this->putJson(route('catalog.families.update', $family), ['name' => $family->name, 'active' => true, 'package_configuration' => $settings]);
    }

    public function test_admin_can_configure_and_audit_service_defaults_without_rewriting_existing_drafts(): void
    {
        $family = $this->family();
        $product = app(SaveProduct::class)->handle($this->admin, ['family_id' => $family->id, 'name' => 'Legacy package', 'active' => true]);
        $before = $product->versions()->sole()->getAttributes();
        $this->update($family, $this->settings())->assertRedirect();
        $this->assertSame('separate_servings', $family->fresh()->package_format);
        $this->assertSame($before, $product->versions()->sole()->getAttributes());
        $audit = AuditEvent::where('action', 'catalog.family_presentation_updated')->latest('id')->firstOrFail();
        $this->assertSame('separate_servings', $audit->after_data['package_configuration']['format']);
        $this->assertNull($audit->before_data['package_configuration']);
        $this->update($family, $this->settings('separate_servings', 2))->assertRedirect();
        $this->assertSame(2, $family->fresh()->default_presentation_quantity);
        $this->assertSame($before, $product->versions()->sole()->getAttributes());
    }

    public function test_established_format_change_is_rejected_by_http_and_database(): void
    {
        $family = $this->family();
        $this->update($family, $this->settings())->assertRedirect();
        app(SaveProduct::class)->handle($this->admin, ['family_id' => $family->id, 'name' => 'Package', 'active' => true]);
        $this->update($family, $this->settings('combined_contents'))->assertUnprocessable()->assertJsonValidationErrors('package_configuration.format');
        $this->expectException(QueryException::class);
        DB::transaction(fn () => DB::table('product_families')->where('id', $family->id)->update(['package_format' => 'combined_contents']));
    }

    public function test_configurable_names_do_not_select_behavior_and_unused_service_format_can_change(): void
    {
        $family = $this->family();
        $this->update($family, $this->settings())->assertRedirect();
        app(UpdateFamilyPresentation::class)->handle($this->admin, $family, ['name' => 'Renamed boxes', 'active' => true, 'package_configuration' => $this->settings('combined_contents')]);
        $this->assertSame('combined_contents', $family->fresh()->package_format);
        $this->assertSame('package', $family->fresh()->behavior_type);
    }

    public function test_malformed_settings_and_inactive_units_are_rejected(): void
    {
        $family = $this->family();
        foreach ([null, 'separate_servings', [], ['format' => 'unknown'], $this->settings('combined_contents', 2), [...$this->settings(), 'quantity' => true]] as $settings) {
            $this->update($family, $settings)->assertUnprocessable();
        }
        $this->unit->update(['active' => false]);
        $this->update($family, $this->settings())->assertUnprocessable()->assertJsonValidationErrors('package_configuration.measurement_unit_id');
        $this->assertNull($family->fresh()->package_format);
    }

    public function test_staff_without_catalog_management_cannot_configure_service(): void
    {
        $family = $this->family();
        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('code', 'staff')->sole());
        $this->actingAs($staff)->withSession(['access_version' => $staff->access_version]);
        $this->update($family, $this->settings())->assertForbidden();
        $this->assertNull($family->fresh()->package_format);
    }

    public function test_individual_sales_reject_package_configuration(): void
    {
        $family = $this->family('direct_sale');
        $this->update($family, $this->settings())->assertUnprocessable()->assertJsonValidationErrors('package_configuration');
        $this->expectException(QueryException::class);
        DB::transaction(fn () => DB::table('product_families')->where('id', $family->id)->update(['package_format' => 'separate_servings', 'default_presentation_quantity' => 1, 'default_presentation_unit_id' => $this->unit->id]));
    }

    public function test_database_rejects_partial_configuration_and_restricts_unit_deletion(): void
    {
        $family = $this->family();
        try {
            DB::transaction(fn () => DB::table('product_families')->where('id', $family->id)->update(['package_format' => 'combined_contents']));
            $this->fail('Incomplete service configuration was accepted.');
        } catch (QueryException $error) {
            $this->assertSame('23514', $error->errorInfo[0]);
        }
        $this->update($family, $this->settings())->assertRedirect();
        $this->expectException(QueryException::class);
        DB::transaction(fn () => $this->unit->delete());
    }

    public function test_create_service_accepts_explicit_configuration_and_audits_it(): void
    {
        $family = app(CreateCollection::class)->handle($this->admin, ['name' => 'Packed meals', 'kind' => 'addon', 'behavior_type' => 'package', 'active' => true, 'package_configuration' => $this->settings('combined_contents')]);
        $this->assertSame('combined_contents', $family->package_format);
        $this->assertSame($this->unit->id, $family->default_presentation_unit_id);
        $audit = AuditEvent::where('action', 'catalog.collection_created')->latest('id')->firstOrFail();
        $this->assertSame('combined_contents', $audit->after_data['package_configuration']['format']);
    }

    public function test_forward_upgrade_preserves_mixed_legacy_publications_and_historical_cloning(): void
    {
        // TestCase has verified the actual isolated connection before this reversible DDL.
        $migration = require database_path('migrations/2026_10_19_000022_add_service_package_configuration.php');
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        $migration->down();
        DB::statement('SET CONSTRAINTS ALL DEFERRED');
        $family = $this->family();
        $category = DishCategory::create(['name' => 'Food', 'code' => 'food', 'active' => true]);
        $food = Dish::create(['name' => 'Included food', 'code' => 'included_food', 'category_id' => $category->id, 'active' => true]);
        $versions = [];
        foreach (['separate_servings', 'combined_contents'] as $format) {
            $product = app(SaveCompletePackage::class)->handle($this->admin, [
                'family_id' => $family->id, 'name' => $format, 'active' => true, 'creation_token' => (string) Str::uuid(),
                'base_price_pesos' => '250', 'price_basis' => 'per_set', 'selection_policy' => 'uniform',
                'minimum_quantity' => 1, 'quantity_step' => 1, 'total_selection_count' => 1,
                'food_item_ids' => [$food->id], 'required_category_ids' => [], 'food_details' => [$food->id => ['is_fixed' => true]],
                'presentation' => [...$this->settings($format), 'food_overrides' => []],
                'save_action' => 'publish', 'confirm_publication' => true,
            ]);
            $version = $product->currentVersion;
            $versions[] = [$product, $version, $version->getAttributes()];
        }
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        $migration->up();
        DB::statement('SET CONSTRAINTS ALL DEFERRED');
        $this->assertNull($family->fresh()->package_format);
        foreach ($versions as [$product, $version, $before]) {
            $this->assertSame($before, $version->fresh()->getAttributes());
            $this->assertSame($version->id, $product->fresh()->current_version_id);
            $draft = app(ClonePackageDraft::class)->handle($this->admin, $product, $version);
            $this->assertSame($version->package_format, $draft->package_format);
            $this->assertSame($version->presentation_unit_id, $draft->presentation_unit_id);
        }
        $this->update($family, $this->settings())->assertRedirect();
        foreach ($versions as [, $version, $before]) {
            $this->assertSame($before, $version->fresh()->getAttributes());
        }
    }
}
