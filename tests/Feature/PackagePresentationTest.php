<?php

namespace Tests\Feature;

use App\Actions\Catalog\CreateCollection;
use App\Actions\Catalog\EnsurePrimaryService;
use App\Actions\Package\ClonePackageDraft;
use App\Actions\Package\PublishPackageVersion;
use App\Actions\Package\SaveCompletePackage;
use App\Actions\Package\SavePackageMenuConfiguration;
use App\Models\Dish;
use App\Models\DishCategory;
use App\Models\MeasurementUnit;
use App\Models\PackageVersion;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Queries\Catalog\DeactivationImpact;
use App\Services\Catalog\CommercialComparison;
use App\Services\Catalog\PackagePricePreview;
use App\Services\Catalog\PackageQuantityRules;
use App\Services\Package\PackagePresentation;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PackagePresentationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private array $data;

    private MeasurementUnit $tray;

    private array $foodIds;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::where('code', 'admin')->sole());
        $this->actingAs($this->admin)->withSession(['access_version' => $this->admin->access_version]);
        $family = app(CreateCollection::class)->handle($this->admin, ['kind' => 'addon', 'name' => 'Flexible meals', 'behavior_type' => 'package', 'active' => true]);
        $category = DishCategory::create(['name' => 'Foods', 'code' => 'format_foods', 'active' => true]);
        $this->foodIds = [];
        foreach (['Roast', 'Rice', 'Drink', 'Vegetables', 'Chicken'] as $name) {
            $this->foodIds[] = Dish::create(['category_id' => $category->id, 'name' => $name, 'code' => strtolower($name), 'active' => true])->id;
        }
        $this->tray = MeasurementUnit::create(['name' => 'Tray', 'abbreviation' => 'tray', 'active' => true]);
        $this->data = ['family_id' => $family->id, 'name' => 'Meal A', 'active' => true, 'creation_token' => (string) Str::uuid(),
            'base_price_pesos' => '149', 'price_basis' => 'per_set', 'selection_policy' => 'uniform', 'minimum_quantity' => 10, 'quantity_step' => 1,
            'total_selection_count' => 5, 'food_item_ids' => $this->foodIds, 'required_category_ids' => [],
            'food_details' => array_fill_keys($this->foodIds, ['is_fixed' => true]),
            'presentation' => ['format' => 'separate_servings', 'quantity' => 1, 'measurement_unit_id' => $this->tray->id, 'food_overrides' => []]];
    }

    private function save(): PackageVersion
    {
        return app(SaveCompletePackage::class)->handle($this->admin, $this->data)->versions()->sole();
    }

    public function test_kilogram_upgrade_keeps_one_tray_and_existing_price_multiplier(): void
    {
        $kg = MeasurementUnit::create(['name' => 'Kilogram', 'abbreviation' => 'kg', 'active' => true]);
        $this->data['food_sizes'] = [$this->foodIds[0] => ['measurement_unit_id' => $kg->id, 'choices' => [
            ['quantity' => '3', 'extra_price_pesos' => '0', 'is_default' => true],
            ['quantity' => '4', 'extra_price_pesos' => '100', 'is_default' => false],
            ['quantity' => '5', 'extra_price_pesos' => '200', 'is_default' => false],
        ]]];
        $version = $this->save();
        $choice = $version->sizeGroups()->sole()->choices()->where('quantity', 5)->sole();
        $preview = app(PackagePricePreview::class)->calculate($version, 10, [['quantity' => 10, 'selections' => ['food_items' => $this->foodIds, 'food_sizes' => [$this->foodIds[0] => $choice->id]]]]);
        $this->assertSame('349000', $preview['total_minor']);
        $this->assertSame('5.000', $preview['groups'][0]['food_items'][0]['quantity_per_unit']);
        foreach ($preview['groups'][0]['food_items'] as $food) {
            $this->assertSame(1, $food['presentation']['quantity']);
            $this->assertSame('Tray', $food['presentation']['measurement_unit']['name']);
        }
    }

    public function test_combined_packs_and_boxes_are_independent_versions_in_one_service(): void
    {
        foreach (['Meal Pack', 'Meal Box'] as $name) {
            $unit = MeasurementUnit::create(['name' => $name, 'abbreviation' => Str::slug($name), 'active' => true]);
            $this->data['creation_token'] = (string) Str::uuid();
            $this->data['name'] = $name;
            $this->data['presentation'] = ['format' => 'combined_contents', 'quantity' => 1, 'measurement_unit_id' => $unit->id];
            $this->data['food_details'][$this->foodIds[0]]['price_adjustment_pesos'] = '10';
            $version = $this->save();
            $preview = app(PackagePricePreview::class)->calculate($version, 10, [['quantity' => 10, 'selections' => ['food_items' => $this->foodIds]]]);
            $this->assertSame('159000', $preview['total_minor']);
            $this->assertSame(10, $preview['quantity']);
            $this->assertCount(5, $preview['groups'][0]['food_items']);
            $this->assertSame(1, $preview['presentation']['default']['quantity']);
            $this->assertSame($name, $preview['presentation']['default']['measurement_unit']['name']);
            foreach ($preview['groups'][0]['food_items'] as $food) {
                $this->assertNull($food['presentation']);
                $this->assertNull($food['quantity_per_unit']);
            }
        }
        $this->assertSame(1, Product::distinct()->count('family_id'));
    }

    public function test_publication_revision_cloning_and_history_preserve_actual_facts(): void
    {
        $this->data['presentation']['food_overrides'] = [$this->foodIds[0] => ['quantity' => 2, 'measurement_unit_id' => $this->tray->id]];
        $version = $this->save();
        $published = app(PublishPackageVersion::class)->handle($this->admin, $version, $version->draft_revision);
        $snapshot = $published->fresh()->snapshot();
        $draft = app(ClonePackageDraft::class)->handle($this->admin, $published->product, $published);
        $this->assertEquals(app(PackagePresentation::class)->input($published), app(PackagePresentation::class)->input($draft));
        $pack = MeasurementUnit::create(['name' => 'Meal Pack', 'abbreviation' => 'pack', 'active' => true]);
        $this->data['presentation'] = ['format' => 'combined_contents', 'quantity' => 1, 'measurement_unit_id' => $pack->id, 'food_overrides' => []];
        app(SavePackageMenuConfiguration::class)->handle($this->admin, $draft, [...$this->data, 'draft_revision' => $draft->draft_revision]);
        $draft->refresh();
        $new = app(PublishPackageVersion::class)->handle($this->admin, $draft, $draft->draft_revision);
        $this->assertSame($snapshot, $published->fresh()->snapshot());
        $changes = collect(app(CommercialComparison::class)->between($snapshot, $new->snapshot()))->keyBy('label');
        $this->assertSame('Separate Food Servings', $changes['Package Format']['before']);
        $this->assertSame('Combined Contents', $changes['Package Format']['after']);
        $pack->update(['name' => 'Renamed box']);
        $this->assertSame('Meal Pack', app(PackagePresentation::class)->effective($new)['default']['measurement_unit']['name']);
    }

    public function test_malformed_incompatible_and_inactive_presentation_is_rejected_atomically(): void
    {
        $invalid = [
            ['format' => ['combined_contents']],
            ['format' => 'combined_contents', 'quantity' => 5, 'measurement_unit_id' => $this->tray->id],
            ['format' => 'combined_contents', 'quantity' => 1, 'measurement_unit_id' => $this->tray->id, 'food_overrides' => [$this->foodIds[0] => ['quantity' => 1, 'measurement_unit_id' => $this->tray->id]]],
            ['format' => 'separate_servings', 'quantity' => 0, 'measurement_unit_id' => $this->tray->id],
            ['format' => 'separate_servings', 'quantity' => true, 'measurement_unit_id' => $this->tray->id],
            ['format' => 'separate_servings', 'quantity' => 1.5, 'measurement_unit_id' => $this->tray->id],
            ['format' => 'separate_servings', 'quantity' => 1],
            ['format' => 'separate_servings', 'food_overrides' => [999999 => ['quantity' => 1, 'measurement_unit_id' => $this->tray->id]]],
        ];
        foreach ($invalid as $presentation) {
            $this->postJson(route('packages.configurations.store'), [...$this->data, 'presentation' => $presentation])->assertUnprocessable();
            $this->assertSame(0, Product::count());
        }
        $this->tray->update(['active' => false]);
        $this->postJson(route('packages.configurations.store'), $this->data)->assertUnprocessable();
        $this->assertSame(0, Product::count());
    }

    public function test_main_catering_and_unauthorized_staff_cannot_set_addon_format(): void
    {
        $main = app(EnsurePrimaryService::class)->handle();
        $this->postJson(route('packages.configurations.store'), [...$this->data, 'family_id' => $main->id, 'price_basis' => 'per_pax'])->assertUnprocessable()->assertJsonValidationErrors('presentation');
        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('code', 'staff')->sole());
        $this->actingAs($staff)->withSession(['access_version' => $staff->access_version])->postJson(route('packages.configurations.store'), $this->data)->assertForbidden();
        $this->assertSame(0, Product::count());
    }

    public function test_database_rejects_invalid_counts_and_published_mutation(): void
    {
        $version = $this->save();
        foreach ([['presentation_quantity' => 0], ['package_format' => 'combined_contents', 'presentation_quantity' => 2], ['package_format' => null], ['presentation_unit_id' => 999999]] as $changes) {
            try {
                DB::transaction(fn () => DB::table('package_versions')->where('id', $version->id)->update($changes));
                $this->fail('Invalid presentation accepted by PostgreSQL.');
            } catch (QueryException $exception) {
                $this->assertContains($exception->getCode(), ['23514', '23503']);
            }
        }
        app(PublishPackageVersion::class)->handle($this->admin, $version, $version->draft_revision);
        $this->expectException(QueryException::class);
        DB::table('package_versions')->where('id', $version->id)->update(['presentation_quantity' => 3]);
    }

    public function test_legacy_unknown_format_is_not_inferred_from_price_basis_or_name(): void
    {
        unset($this->data['presentation']);
        $this->data['price_basis'] = 'per_pack';
        $this->data['name'] = 'Food Pack Lunch';
        $version = $this->save();
        $published = app(PublishPackageVersion::class)->handle($this->admin, $version, $version->draft_revision);
        $this->assertNull(app(PackagePresentation::class)->effective($published));
        $this->assertNull(app(ClonePackageDraft::class)->handle($this->admin, $published->product, $published)->package_format);
    }

    public function test_new_formats_reject_split_and_presentation_unit_usage_is_visible(): void
    {
        $this->postJson(route('packages.configurations.store'), [...$this->data, 'price_basis' => 'per_pack', 'selection_policy' => 'split'])
            ->assertUnprocessable()->assertJsonValidationErrors('presentation');
        $this->assertSame(0, Product::count());
        $version = $this->save();
        $impact = app(DeactivationImpact::class)->forRecord($this->tray);
        $this->assertSame($version->id, $impact['usage'][0]['version_id']);
        $published = app(PublishPackageVersion::class)->handle($this->admin, $version, $version->draft_revision);
        $snapshot = $published->fresh()->snapshot();
        $this->tray->update(['active' => false]);
        $this->assertSame($snapshot, $published->fresh()->snapshot());
        $draft = app(ClonePackageDraft::class)->handle($this->admin, $published->product, $published);
        $this->expectException(ValidationException::class);
        app(PublishPackageVersion::class)->handle($this->admin, $draft, $draft->draft_revision);
    }

    public function test_database_rejects_food_overrides_for_combined_contents_and_protects_published_children(): void
    {
        $this->data['presentation']['format'] = 'combined_contents';
        $version = $this->save();
        try {
            DB::transaction(function () use ($version): void {
                DB::table('package_version_food_items')->where('package_version_id', $version->id)
                    ->update(['presentation_quantity' => 1, 'presentation_unit_id' => $this->tray->id]);
                DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
            });
            $this->fail('Combined food-container override was accepted.');
        } catch (QueryException $exception) {
            $this->assertSame('23514', $exception->getCode());
        }
        app(PublishPackageVersion::class)->handle($this->admin, $version, $version->draft_revision);
        $this->expectException(QueryException::class);
        DB::table('package_version_food_items')->where('package_version_id', $version->id)
            ->update(['presentation_quantity' => 1, 'presentation_unit_id' => $this->tray->id]);
    }

    public function test_quantity_errors_use_current_configured_unit_and_frozen_unit_after_publication(): void
    {
        $this->tray->update(['name' => 'Meal Box']);
        $this->data['presentation']['format'] = 'combined_contents';
        $version = $this->save();
        $version->family->forceFill(['name' => 'Renamed service'])->save();
        $version->product->update(['name' => 'Renamed package']);
        foreach ([$version, app(PublishPackageVersion::class)->handle($this->admin, $version, $version->draft_revision)] as $terms) {
            if ($terms->status === 'published') {
                $this->tray->update(['name' => 'Changed master unit']);
            }
            try {
                app(PackageQuantityRules::class)->validate($terms, 9);
                $this->fail('Minimum quantity was not enforced.');
            } catch (ValidationException $exception) {
                $message = $exception->errors()['quantity'][0];
                $this->assertStringContainsString('Renamed service', $message);
                $this->assertStringContainsString('Renamed package', $message);
                $this->assertStringContainsString('minimum of 10 complete Meal Box units', $message);
            }
        }
    }
}
