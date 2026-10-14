<?php

namespace Tests\Feature;

use App\Actions\Package\AbandonPackageDraft;
use App\Actions\Package\ChangePackageAvailability;
use App\Actions\Package\ClonePackageDraft;
use App\Actions\Package\PublishPackageVersion;
use App\Actions\Package\SaveProduct;
use App\Actions\Package\SaveSelectionGroup;
use App\Models\Dish;
use App\Models\DishCategory;
use App\Models\DishOffering;
use App\Models\OfferingOption;
use App\Models\PackageVersion;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\Role;
use App\Models\User;
use App\Services\Package\EffectivePackageAvailability;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\LegacyCollections;
use Tests\TestCase;

class PackagePublicationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private ProductFamily $family;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        LegacyCollections::seed();
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::where('code', 'admin')->sole());
        $this->family = ProductFamily::where('code', 'belly_lechon')->sole();
    }

    private function product(): Product
    {
        return app(SaveProduct::class)->handle($this->admin, ['family_id' => $this->family->id,
            'name' => 'Package A', 'description' => null, 'active' => true]);
    }

    private function option(string $name): OfferingOption
    {
        $category = DishCategory::firstOrCreate(['code' => 'meat'],
            ['name' => 'Meat', 'sort_order' => 1, 'active' => true]);
        $dish = Dish::create(['code' => strtolower(str_replace(' ', '_', $name)), 'category_id' => $category->id,
            'name' => $name, 'active' => true]);
        DB::table('dish_family_eligibilities')->insert(['dish_id' => $dish->id,
            'family_id' => $this->family->id, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $offering = DishOffering::create(['dish_id' => $dish->id, 'family_id' => $this->family->id,
            'code' => 'standard', 'name' => 'Standard', 'active' => true]);

        return OfferingOption::create(['offering_id' => $offering->id, 'option_code' => 'regular',
            'label' => 'Regular', 'unit' => 'tray', 'quantity' => '1.000', 'active' => true]);
    }

    private function group(PackageVersion $version, OfferingOption $option, string $name): void
    {
        app(SaveSelectionGroup::class)->handle($this->admin, $version, [
            'draft_revision' => $version->fresh()->draft_revision,
            'name' => $name, 'mode' => 'select_one', 'slot_kind' => 'item',
            'minimum_count' => 1, 'maximum_count' => 1, 'unique_dishes' => true,
            'category_mode' => 'free', 'inclusion_count' => '1.000',
            'options' => [['offering_option_id' => $option->id, 'is_default' => true,
                'price_adjustment_minor' => '0']], 'category_rules' => [], 'dish_rules' => [],
        ]);
    }

    public function test_new_package_has_one_initial_draft(): void
    {
        $product = $this->product();
        $this->assertStringStartsWith('product_', $product->code);
        $package = $product;
        $this->assertSame(1, $package->versions()->count());
        $this->assertSame('draft', $package->versions()->sole()->status);
        app(SaveProduct::class)->handle($this->admin, ['family_id' => $this->family->id,
            'name' => 'Package A Updated', 'description' => null, 'active' => true], $product);
        $this->assertSame(1, $package->versions()->count());
    }

    public function test_http_creation_ignores_a_supplied_technical_code_and_order(): void
    {
        $payload = ['family_id' => $this->family->id, 'code' => 'forged', 'sort_order' => 900,
            'name' => 'Package B', 'description' => null, 'active' => true,
            'creation_token' => 'c917b7bf-bc5f-4464-ae50-66fdb17bc802'];
        $this->actingAs($this->admin)->withSession(['access_version' => $this->admin->access_version])
            ->post(route('packages.store'), $payload)->assertRedirect();
        $this->post(route('packages.store'), $payload)->assertRedirect();
        $product = Product::where('name', 'Package B')->sole();
        $this->assertStringStartsWith('product_', $product->code);
        $this->assertSame(1, $product->sort_order);
        $this->assertSame(1, $product->versions()->count());
        $this->assertSame(1, Product::where('family_id', $this->family->id)->count());
    }

    public function test_publication_freezes_names_and_recovers_dynamic_availability(): void
    {
        $product = $this->product();
        $package = $product;
        $draft = $package->versions()->sole();
        $option = $this->option('Chicken Adobo');
        $this->group($draft, $option, 'Choose a dish');
        $published = app(PublishPackageVersion::class)->handle($this->admin, $draft, $draft->fresh()->draft_revision);
        $this->assertSame('published', $published->status);
        $this->assertSame($published->id, $package->fresh()->current_version_id);
        $this->assertSame('Chicken Adobo', $published->published_snapshot['groups'][0]['options'][0]['food_item']['name']);
        $option->offering->dish->update(['name' => 'Renamed Dish']);
        $this->assertSame('Chicken Adobo', $published->fresh()->published_snapshot['groups'][0]['options'][0]['food_item']['name']);
        $this->assertTrue(app(EffectivePackageAvailability::class)->forProduct($package->fresh())['available']);
        $option->update(['active' => false]);
        $this->assertFalse(app(EffectivePackageAvailability::class)->forProduct($package->fresh())['available']);
        $option->update(['active' => true]);
        $this->assertTrue(app(EffectivePackageAvailability::class)->forProduct($package->fresh())['available']);
        $retry = app(PublishPackageVersion::class)->handle($this->admin, $draft, $draft->fresh()->draft_revision);
        $this->assertSame($published->id, $retry->id);
    }

    public function test_impossible_cross_group_duplicate_blocks_publication_without_losing_draft(): void
    {
        $product = $this->product();
        $draft = $product->versions()->sole();
        $option = $this->option('Shared Dish');
        $this->group($draft, $option, 'Choice One');
        $this->group($draft, $option, 'Choice Two');
        try {
            app(PublishPackageVersion::class)->handle($this->admin, $draft, $draft->fresh()->draft_revision);
            $this->fail('Impossible configuration was published.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('groups', $exception->errors());
        }
        $this->assertSame('draft', $draft->fresh()->status);
        $this->assertNull($product->current_version_id);
    }

    public function test_group_changes_advance_revision_and_stale_group_edits_are_rejected(): void
    {
        $draft = $this->product()->versions()->sole();
        $option = $this->option('Rice Dish');
        $this->group($draft, $option, 'Choose Rice');
        $this->assertSame(2, $draft->fresh()->draft_revision);
        try {
            app(SaveSelectionGroup::class)->handle($this->admin, $draft, [
                'draft_revision' => 1, 'name' => 'Stale', 'mode' => 'fixed', 'slot_kind' => 'item',
                'minimum_count' => 1, 'maximum_count' => 1, 'unique_dishes' => true,
                'category_mode' => 'free', 'inclusion_count' => '1.000',
                'options' => [['offering_option_id' => $option->id, 'is_default' => true,
                    'price_adjustment_minor' => '0']], 'category_rules' => [], 'dish_rules' => [],
            ]);
            $this->fail('Stale group edit was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('draft_revision', $exception->errors());
        }
        $this->assertSame(1, $draft->groups()->count());
    }

    public function test_revision_clones_history_and_abandonment_does_not_change_current_version(): void
    {
        $product = $this->product();
        $package = $product;
        $draft = $package->versions()->sole();
        $this->group($draft, $this->option('Pork Dish'), 'Choose Pork');
        $published = app(PublishPackageVersion::class)->handle($this->admin, $draft, $draft->fresh()->draft_revision);
        $revision = app(ClonePackageDraft::class)->handle($this->admin, $package, $published);
        $this->assertSame(2, $revision->version_number);
        $this->assertSame($published->id, $revision->cloned_from_id);
        $this->assertSame(1, $revision->groups()->count());
        app(AbandonPackageDraft::class)->handle($this->admin, $revision, 1);
        $this->assertSame('abandoned', $revision->fresh()->status);
        $this->assertSame($published->id, $package->fresh()->current_version_id);
        $next = app(ClonePackageDraft::class)->handle($this->admin, $package, $published);
        $this->assertSame(3, $next->version_number);
    }

    public function test_published_rows_reject_direct_updates(): void
    {
        $product = $this->product();
        $draft = $product->versions()->sole();
        $this->group($draft, $this->option('Beef Dish'), 'Choose Beef');
        $published = app(PublishPackageVersion::class)->handle($this->admin, $draft, $draft->fresh()->draft_revision);
        $this->expectException(QueryException::class);
        DB::table('package_versions')->where('id', $published->id)->update(['base_price_minor' => 999]);
    }

    public function test_database_rejects_technical_code_changes(): void
    {
        $product = $this->product();
        $this->expectException(QueryException::class);
        DB::table('products')->where('id', $product->id)->update(['code' => 'changed']);
    }

    public function test_staff_requires_separate_publication_and_history_permissions(): void
    {
        $product = $this->product();
        $package = $product;
        $draft = $package->versions()->sole();
        $this->group($draft, $this->option('Fish Dish'), 'Choose Fish');
        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('code', 'staff')->sole());
        $path = route('packages.versions.publish', [$product, $draft]);
        $payload = ['draft_revision' => $draft->fresh()->draft_revision, 'confirm' => true];
        $this->actingAs($staff)->withSession(['access_version' => $staff->access_version])
            ->post($path, $payload)->assertForbidden();
        $this->assertSame('draft', $draft->fresh()->status);
        $staff->permissionOverrides()->attach(Permission::where('code', 'package.publish')->sole(), ['effect' => 'allow']);
        $staff->unsetRelations();
        $this->actingAs($staff)->withSession(['access_version' => $staff->access_version])
            ->post($path, $payload)->assertRedirect();
        $this->assertSame('published', $draft->fresh()->status);
        $show = route('packages.versions.show', [$product, $draft]);
        $this->actingAs($staff)->withSession(['access_version' => $staff->access_version])->get($show)->assertForbidden();
        $staff->permissionOverrides()->attach(Permission::where('code', 'package.view')->sole(), ['effect' => 'allow']);
        $staff->unsetRelations();
        $this->actingAs($staff)->withSession(['access_version' => $staff->access_version])->get($show)->assertForbidden();
        $staff->permissionOverrides()->attach(Permission::where('code', 'package.history.view')->sole(), ['effect' => 'allow']);
        $staff->unsetRelations();
        $this->actingAs($staff)->withSession(['access_version' => $staff->access_version])->get($show)->assertOk();
    }

    public function test_staff_publish_permission_does_not_authorize_new_commercial_prices(): void
    {
        $product = $this->product();
        $package = $product;
        $draft = $package->versions()->sole();
        $draft->update(['base_price_minor' => 25000]);
        $this->group($draft, $this->option('Priced Dish'), 'Choose Dish');
        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('code', 'staff')->sole());
        $staff->permissionOverrides()->attach(Permission::where('code', 'package.publish')->sole(), ['effect' => 'allow']);
        $this->actingAs($staff)->withSession(['access_version' => $staff->access_version])
            ->post(route('packages.versions.publish', [$product, $draft]),
                ['draft_revision' => $draft->fresh()->draft_revision, 'confirm' => true])->assertForbidden();
        $this->assertSame('draft', $draft->fresh()->status);
        $this->assertNull($package->fresh()->current_version_id);
    }

    public function test_staff_without_history_permission_cannot_receive_published_snapshot_on_product_page(): void
    {
        $product = $this->product();
        $package = $product;
        $draft = $package->versions()->sole();
        $this->group($draft, $this->option('Private Published Dish'), 'Choose Dish');
        app(PublishPackageVersion::class)->handle($this->admin, $draft, $draft->fresh()->draft_revision);

        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('code', 'staff')->sole());
        $staff->permissionOverrides()->attach(Permission::where('code', 'package.view')->sole(), ['effect' => 'allow']);

        $this->actingAs($staff)->withSession(['access_version' => $staff->access_version])
            ->get(route('packages.show', $product))->assertOk()
            ->assertDontSee('published_snapshot')
            ->assertDontSee('Private Published Dish');
    }

    public function test_two_packages_publish_independently_and_current_pointer_cannot_cross_owners(): void
    {
        $product = $this->product();
        $first = $product;
        $firstDraft = $first->versions()->sole();
        $this->group($firstDraft, $this->option('First Variant Dish'), 'First Choice');
        $firstPublished = app(PublishPackageVersion::class)->handle(
            $this->admin, $firstDraft, $firstDraft->fresh()->draft_revision
        );
        $second = app(SaveProduct::class)->handle($this->admin, ['family_id' => $this->family->id, 'name' => 'Premium', 'active' => true]);
        $secondDraft = $second->versions()->sole();
        $this->group($secondDraft, $this->option('Second Variant Dish'), 'Second Choice');
        $secondPublished = app(PublishPackageVersion::class)->handle(
            $this->admin, $secondDraft, $secondDraft->fresh()->draft_revision
        );
        $this->assertNotSame($firstPublished->id, $secondPublished->id);
        $this->assertSame($firstPublished->id, $first->fresh()->current_version_id);
        $this->assertSame($secondPublished->id, $secondDraft->product->fresh()->current_version_id);
        $this->expectException(QueryException::class);
        DB::table('products')->where('id', $first->id)
            ->update(['current_version_id' => $secondPublished->id]);
    }

    public function test_one_active_draft_per_package_is_a_postgresql_constraint(): void
    {
        $draft = $this->product()->versions()->sole();
        $this->expectException(QueryException::class);
        DB::table('package_versions')->insert([
            'package_product_id' => $draft->package_product_id, 'family_id' => $draft->family_id,
            'version_number' => 2, 'status' => 'draft', 'draft_revision' => 1,
            'created_by' => $this->admin->id, 'currency' => 'PHP',
            'price_basis' => $draft->price_basis, 'base_price_minor' => 0,
            'selection_policy' => 'uniform', 'minimum_quantity' => 1, 'quantity_step' => 1,
        ]);
    }

    public function test_published_package_can_be_disabled_and_archive_preserves_history(): void
    {
        $product = $this->product();
        $package = $product;
        $draft = $package->versions()->sole();
        $this->group($draft, $this->option('Archive Dish'), 'Choice');
        $published = app(PublishPackageVersion::class)->handle($this->admin, $draft, $draft->fresh()->draft_revision);
        app(ChangePackageAvailability::class)->handle($this->admin, $product, 'disable');
        $this->assertFalse($product->fresh()->active);
        $this->assertFalse(app(EffectivePackageAvailability::class)->forProduct($package->fresh())['available']);
        app(ChangePackageAvailability::class)->handle($this->admin, $product, 'archive');
        $this->assertNotNull($product->fresh()->archived_at);
        $this->assertSame($published->id, $package->fresh()->current_version_id);
        $this->assertSame('published', $published->fresh()->status);
    }

    public function test_http_disable_does_not_require_archive_confirmation(): void
    {
        $product = $this->product();

        $this->actingAs($this->admin)->withSession(['access_version' => $this->admin->access_version])
            ->post(route('packages.products.availability', $product), ['operation' => 'disable'])
            ->assertRedirect(route('packages.show', $product));

        $this->assertFalse($product->fresh()->active);
    }

    public function test_draft_edit_permissions_cannot_bypass_separate_lifecycle_authority(): void
    {
        $product = $this->product();
        $package = $product;
        $draft = $package->versions()->sole();
        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('code', 'staff')->sole());
        foreach (['package.manage', 'package.pricing.manage'] as $code) {
            $staff->permissionOverrides()->attach(Permission::where('code', $code)->sole(), ['effect' => 'allow']);
        }
        $this->actingAs($staff)->withSession(['access_version' => $staff->access_version]);
        $this->put(route('packages.update', $product), [
            'family_id' => $product->family_id, 'name' => $product->name,
            'description' => null, 'active' => false,
        ])->assertForbidden();
        $this->assertTrue($product->fresh()->active);
        $this->post(route('packages.products.availability', $product), ['operation' => 'disable'])->assertForbidden();
        $this->assertTrue($product->fresh()->active);
    }

    public function test_package_product_cannot_move_to_another_collection(): void
    {
        $product = $this->product();
        $other = ProductFamily::where('code', 'pica_pica')->sole();
        try {
            app(SaveProduct::class)->handle($this->admin, [
                'family_id' => $other->id, 'name' => $product->name,
                'description' => null, 'active' => true,
            ], $product);
            $this->fail('Package Product moved between Collections.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('family_id', $exception->errors());
        }
        $this->assertSame($this->family->id, $product->fresh()->family_id);
    }
}
