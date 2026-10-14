<?php

namespace Tests\Feature;

use App\Actions\Catalog\EnsurePrimaryService;
use App\Actions\Package\ClonePackageDraft;
use App\Actions\Package\PublishPackageVersion;
use App\Actions\Package\SavePackageMenuConfiguration;
use App\Actions\Package\SavePackageVersion;
use App\Actions\Package\SaveProduct;
use App\Models\Dish;
use App\Models\DishCategory;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\Role;
use App\Models\User;
use App\Services\Catalog\PackagePricePreview;
use App\Services\Package\EffectivePackageAvailability;
use App\Services\Package\PackageMenuConfiguration;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BusinessWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private ProductFamily $family;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::where('code', 'admin')->sole());
        $this->family = app(EnsurePrimaryService::class)->handle();
        $this->actingAs($this->admin)->withSession(['access_version' => $this->admin->access_version]);
    }

    private function menu(): array
    {
        $product = app(SaveProduct::class)->handle($this->admin, ['family_id' => $this->family->id, 'name' => 'Package '.Product::count(), 'active' => true]);
        $items = collect();
        $categories = collect();
        foreach (['Staple' => ['One'], 'Drink' => ['Two', 'Three'], 'Main' => ['Four', 'Five']] as $name => $foods) {
            $category = DishCategory::firstOrCreate(['code' => strtolower($name)], ['name' => $name, 'active' => true]);
            $categories->push($category);
            foreach ($foods as $name) {
                $food = Dish::firstOrCreate(['code' => strtolower($name)], ['name' => $name, 'category_id' => $category->id, 'active' => true]);
                $food->eligibleFamilies()->syncWithoutDetaching([$this->family->id => ['active' => true]]);
                $items->push($food);
            }
        }
        $draft = $product->versions()->sole();
        app(SavePackageMenuConfiguration::class)->handle($this->admin, $draft, ['draft_revision' => 1,
            'total_selection_count' => 3, 'food_item_ids' => $items->pluck('id')->all(),
            'required_category_ids' => $categories->take(2)->pluck('id')->all()]);

        return [$product, $draft->fresh(), $items, $categories];
    }

    private function invalid(callable $work, string $field): void
    {
        try {
            $work();
            $this->fail('Invalid operation accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    public function test_primary_bootstrap_is_idempotent_preserves_disabled_identity_and_replaces_only_archived_primary(): void
    {
        $this->assertSame(1, ProductFamily::count());
        $this->family->forceFill(['name' => 'Owner Catering', 'active' => false])->save();
        $this->seed(DatabaseSeeder::class);
        $this->assertSame($this->family->id, app(EnsurePrimaryService::class)->handle()->id);
        $this->assertFalse($this->family->fresh()->active);
        $this->get(route('catering.create'))->assertRedirect(route('catering.index'));
        $this->get(route('catering.index'))->assertInertia(fn ($page) => $page->where('serviceActive', false));
        $this->post(route('catering.store'), ['name' => 'Blocked', 'active' => true, 'creation_token' => (string) str()->uuid()])->assertSessionHasErrors('family_id');
        $this->family->forceFill(['archived_at' => now()])->save();
        $replacement = app(EnsurePrimaryService::class)->handle();
        $this->assertNotSame($this->family->id, $replacement->id);
        $this->assertSame(2, ProductFamily::count());
        $this->assertNotNull($this->family->fresh()->archived_at);
        $this->assertSame($replacement->id, app(EnsurePrimaryService::class)->handle()->id);
    }

    public function test_main_creation_has_no_collection_input_and_scoped_lists_never_mix_addons(): void
    {
        $this->post(route('catering.store'), ['name' => 'A', 'active' => true, 'creation_token' => (string) str()->uuid()])->assertRedirect();
        $this->post(route('services.store'), ['name' => 'Extras', 'kind' => 'addon', 'behavior_type' => 'package', 'active' => true])->assertRedirect();
        $addon = ProductFamily::where('name', 'Extras')->sole();
        app(SaveProduct::class)->handle($this->admin, ['family_id' => $addon->id, 'name' => 'Extra', 'active' => true]);
        $this->get(route('catering.index'))->assertInertia(fn ($page) => $page->has('products.data', 1)->where('products.data.0.name', 'A'));
        $this->get(route('services.show', $addon))->assertInertia(fn ($page) => $page->has('products', 1)->where('products.0.name', 'Extra'));
        $this->post(route('catering.store'), ['family_id' => $addon->id, 'name' => 'Forged', 'active' => true, 'creation_token' => (string) str()->uuid()])->assertSessionHasErrors('family_id');
    }

    public function test_menu_requires_exact_distinct_foods_and_each_required_category(): void
    {
        [, $draft, $items] = $this->menu();
        $rules = app(PackageMenuConfiguration::class);
        $valid = [$items[0]->id, $items[1]->id, $items[3]->id];
        $this->assertCount(3, $rules->validateCustomerSelection($draft, $valid));
        foreach ([[true, $items[1]->id, $items[3]->id], [[$items[0]->id], $items[1]->id, $items[3]->id], [$items[0]->id, $items[0]->id, $items[1]->id], array_slice($valid, 0, 2), [$items[0]->id, $items[3]->id, $items[4]->id], [$items[1]->id, $items[3]->id, $items[4]->id], [...$valid, $items[4]->id]] as $ids) {
            $this->invalid(fn () => $rules->validateCustomerSelection($draft, $ids), 'food_items');
        }
        $this->assertCount(0, $draft->groups);
    }

    public function test_impossible_required_categories_roll_back_menu_and_revision(): void
    {
        [, $draft, $items, $categories] = $this->menu();
        $revision = $draft->draft_revision;
        $this->invalid(fn () => app(SavePackageMenuConfiguration::class)->handle($this->admin, $draft, [
            'draft_revision' => $revision, 'total_selection_count' => 1, 'food_item_ids' => $items->pluck('id')->all(), 'required_category_ids' => $categories->pluck('id')->all(),
        ]), 'total_selection_count');
        $this->assertSame($revision, $draft->fresh()->draft_revision);
        $this->assertSame(3, $draft->fresh()->total_selection_count);
    }

    public function test_main_preview_calculates_centavos_with_package_minimum_step_and_stale_edit_checks(): void
    {
        [$product, $draft, $items] = $this->menu();
        $data = ['base_price_pesos' => '850.25', 'selection_policy' => 'uniform', 'minimum_quantity' => 150,
            'quantity_step' => 10,  'maximum_quantity' => null, 'total_selection_count' => 3, 'draft_revision' => $draft->draft_revision];
        app(SavePackageVersion::class)->handle($this->admin, $product, $data, $draft);
        $preview = fn (int $quantity) => app(PackagePricePreview::class)->calculate($draft->fresh(), $quantity, [['quantity' => $quantity, 'selections' => ['food_items' => [$items[0]->id, $items[1]->id, $items[3]->id]]]]);
        $this->assertSame('12753750', $preview(150)['total_minor']);
        $this->assertSame([], $preview(150)['groups'][0]['adjustments']);
        foreach ([149, 151] as $quantity) {
            $this->invalid(fn () => $preview($quantity), 'quantity');
        }
        app(SavePackageVersion::class)->handle($this->admin, $product, [...$data, 'minimum_quantity' => 140, 'draft_revision' => $draft->fresh()->draft_revision], $draft);
        $this->assertSame(140, $draft->fresh()->minimum_quantity);
        $this->invalid(fn () => app(SavePackageVersion::class)->handle($this->admin, $product, $data, $draft), 'draft_revision');
        $this->family->forceFill(['name' => 'Celebration Menus'])->save();
        $this->assertSame('12753750', $preview(150)['total_minor']);
    }

    public function test_published_main_availability_uses_frozen_categories_and_live_eligibility(): void
    {
        [$product, $draft, $items, $categories] = $this->menu();
        $published = app(PublishPackageVersion::class)->handle($this->admin, $draft, $draft->draft_revision);
        $snapshot = $published->fresh()->snapshot();
        $items[0]->update(['category_id' => $categories[2]->id, 'name' => 'Renamed Staple']);
        $this->assertTrue(app(EffectivePackageAvailability::class)->forProduct($product->fresh())['available']);
        $this->assertCount(3, app(PackageMenuConfiguration::class)->validateCustomerSelection($published, [$items[0]->id, $items[1]->id, $items[3]->id]));
        $items[0]->eligibleFamilies()->updateExistingPivot($this->family->id, ['active' => false]);
        $this->assertFalse(app(EffectivePackageAvailability::class)->forProduct($product->fresh())['available']);
        $this->assertSame($snapshot, $published->fresh()->snapshot());
    }

    public function test_main_legacy_group_editor_is_rejected_and_published_menu_cannot_be_reparented(): void
    {
        [$product, $draft] = $this->menu();
        $this->get(route('packages.groups.create', [$product, $draft]))->assertNotFound();
        $published = app(PublishPackageVersion::class)->handle($this->admin, $draft, $draft->draft_revision);
        $revision = app(ClonePackageDraft::class)->handle($this->admin, $product, $published);
        $this->expectException(QueryException::class);
        DB::table('package_version_food_items')->where('package_version_id', $published->id)->update(['package_version_id' => $revision->id]);
    }

    public function test_published_ownership_and_snapshot_cannot_change_and_history_pointer_never_moves_backward(): void
    {
        [$product, $draft] = $this->menu();
        $published = app(PublishPackageVersion::class)->handle($this->admin, $draft, $draft->draft_revision);
        $revision = app(ClonePackageDraft::class)->handle($this->admin, $product, $published);
        app(PublishPackageVersion::class)->handle($this->admin, $revision, $revision->draft_revision);
        foreach ([
            fn () => DB::table('package_versions')->where('id', $published->id)->update(['published_snapshot' => '{}']),
            fn () => DB::table('products')->where('id', $product->id)->update(['current_version_id' => $published->id]),
            fn () => DB::table('package_versions')->where('id', $published->id)->delete(),
        ] as $write) {
            DB::statement('SAVEPOINT immutable_probe');
            try {
                $write();
                $this->fail('Immutable publication was changed.');
            } catch (QueryException $exception) {
                $this->assertSame('23514', $exception->errorInfo[0]);
            } finally {
                DB::statement('ROLLBACK TO SAVEPOINT immutable_probe');
            }
        }
    }
}
