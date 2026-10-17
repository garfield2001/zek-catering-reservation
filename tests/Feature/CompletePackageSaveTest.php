<?php

namespace Tests\Feature;

use App\Actions\Catalog\CreateCollection;
use App\Actions\Catalog\EnsurePrimaryService;
use App\Actions\Package\ClonePackageDraft;
use App\Actions\Package\PublishPackageVersion;
use App\Models\AuditEvent;
use App\Models\Dish;
use App\Models\DishCategory;
use App\Models\PackageVersion;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Queries\Catalog\FoodUsage;
use App\Services\Package\PackageMenuConfiguration;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompletePackageSaveTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private array $input;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::where('code', 'admin')->sole());
        $this->actingAs($this->admin)->withSession(['access_version' => $this->admin->access_version]);
        $family = app(EnsurePrimaryService::class)->handle();
        $category = DishCategory::create(['code' => 'rice', 'name' => 'Rice', 'active' => true]);
        $ids = [];
        foreach (['Fried Rice', 'Steamed Rice'] as $index => $name) {
            $food = Dish::create(['code' => 'rice_'.$index, 'name' => $name, 'category_id' => $category->id, 'active' => true]);
            $ids[] = $food->id;
        }
        $this->input = ['family_id' => $family->id, 'name' => 'Package B', 'description' => 'Complete saved configuration', 'active' => true,
            'creation_token' => (string) Str::uuid(), 'total_selection_count' => 2, 'food_item_ids' => $ids,
            'required_category_ids' => [$category->id], 'food_details' => [$ids[0] => ['is_fixed' => true]],
            'base_price_pesos' => '850.00', 'price_basis' => 'per_pax', 'selection_policy' => 'uniform',
            'minimum_quantity' => 150, 'quantity_step' => 10, 'maximum_quantity' => 500];
    }

    public function test_modal_json_editor_and_save_share_permissions_and_persist_complete_configuration(): void
    {
        $this->getJson(route('packages.editor', ['service' => $this->input['family_id']]))
            ->assertOk()->assertJsonPath('canPublish', true)->assertJsonPath('servingDefault', null);
        $response = $this->postJson(route('packages.configurations.store'), $this->input)->assertCreated();
        $product = Product::findOrFail($response->json('id'));
        $this->getJson(route('packages.edit', $product))->assertOk()->assertJsonPath('product.id', $product->id)
            ->assertJsonPath('version.minimum_quantity', 150);
        $this->assertSame('85000', (string) $product->versions()->sole()->base_price_minor);
        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('code', 'staff')->sole());
        $this->actingAs($staff)->withSession(['access_version' => $staff->access_version]);
        $this->getJson(route('packages.edit', $product))->assertForbidden();
        $this->getJson(route('packages.editor', ['service' => $product->family_id]))->assertForbidden();
        $this->postJson(route('packages.configurations.store'), $this->input)->assertForbidden();
        $this->assertSame(1, Product::count());
    }

    public function test_complete_save_is_atomic_unpublished_and_idempotent(): void
    {
        $this->post(route('packages.configurations.store'), $this->input)->assertSessionHasNoErrors()->assertRedirect();
        $product = Product::sole();
        $version = $product->versions()->sole();
        $this->assertSame('draft', $version->status);
        $this->assertNull($product->current_version_id);
        $this->assertSame('85000', (string) $version->base_price_minor);
        $this->assertSame(150, $version->minimum_quantity);
        $this->assertCount(2, $version->foodItems);
        $this->assertTrue((bool) $version->foodItems->first()->pivot->always_include);
        app(PackageMenuConfiguration::class)->validateDraft($version);
        $audits = AuditEvent::count();
        $this->post(route('packages.configurations.store'), $this->input)->assertSessionHasNoErrors();
        $this->assertSame(1, Product::count());
        $this->assertSame(1, PackageVersion::count());
        $this->assertSame($audits, AuditEvent::count());
        $this->post(route('packages.configurations.store'), [...$this->input, 'base_price_pesos' => '900.00'])->assertSessionHasErrors('creation_token');
    }

    public function test_invalid_menu_rolls_back_product_version_and_all_success_audits(): void
    {
        $audits = AuditEvent::count();
        $this->post(route('packages.configurations.store'), [...$this->input, 'total_selection_count' => 3])->assertSessionHasErrors('food_items');
        $this->assertSame(0, Product::count());
        $this->assertSame(0, PackageVersion::count());
        $this->assertSame($audits, AuditEvent::count());
    }

    public function test_save_and_publish_is_explicit_atomic_and_idempotent(): void
    {
        $data = [...$this->input, 'save_action' => 'publish'];
        $this->post(route('packages.configurations.store'), $data)->assertSessionHasErrors('confirm_publication');
        $this->assertSame(0, Product::count());
        $data['confirm_publication'] = true;
        $this->post(route('packages.configurations.store'), $data)->assertSessionHasNoErrors();
        $product = Product::sole();
        $version = $product->versions()->sole();
        $this->assertSame('published', $version->status);
        $this->assertSame($version->id, $product->current_version_id);
        $this->assertSame($this->admin->id, $version->published_by);
        $this->assertSame('85000', $version->snapshot()['version']['base_price_minor']);
        $audits = AuditEvent::count();
        $this->post(route('packages.configurations.store'), $data)->assertSessionHasNoErrors();
        $this->assertSame(1, PackageVersion::count());
        $this->assertSame($audits, AuditEvent::count());
    }

    public function test_publication_failure_rolls_back_entire_wizard_save(): void
    {
        $audits = AuditEvent::count();
        $this->post(route('packages.configurations.store'), [...$this->input, 'active' => false,
            'save_action' => 'publish', 'confirm_publication' => true])->assertSessionHasErrors('version');
        $this->assertSame(0, Product::count());
        $this->assertSame(0, PackageVersion::count());
        $this->assertSame($audits, AuditEvent::count());
    }

    public function test_publish_changes_creates_new_version_without_mutating_published_history(): void
    {
        $this->post(route('packages.configurations.store'), [...$this->input, 'save_action' => 'publish', 'confirm_publication' => true])->assertSessionHasNoErrors();
        $product = Product::sole();
        $original = $product->currentVersion;
        $snapshot = $original->snapshot();
        $data = $this->input;
        unset($data['creation_token']);
        $this->put(route('packages.configurations.update', $product), [...$data, 'version_id' => $original->id,
            'draft_revision' => $original->draft_revision, 'base_price_pesos' => '900.00',
            'save_action' => 'publish', 'confirm_publication' => true])->assertSessionHasNoErrors();
        $this->assertSame($snapshot, $original->fresh()->snapshot());
        $this->assertNotSame($original->id, $product->fresh()->current_version_id);
        $this->assertSame('90000', $product->fresh()->currentVersion->snapshot()['version']['base_price_minor']);
        $this->assertSame(0, $product->versions()->where('status', 'draft')->count());
    }

    public function test_edit_and_price_permissions_do_not_allow_forged_save_and_publish(): void
    {
        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('code', 'staff')->sole());
        foreach (['package.manage', 'package.pricing.manage'] as $code) {
            $staff->permissionOverrides()->attach(Permission::where('code', $code)->sole(), ['effect' => 'allow']);
        }
        $this->actingAs($staff)->withSession(['access_version' => $staff->access_version]);
        $this->post(route('packages.configurations.store'), [...$this->input, 'save_action' => 'publish', 'confirm_publication' => true])->assertForbidden();
        $this->assertSame(0, Product::count());
    }

    public function test_published_edit_creates_one_draft_and_preserves_snapshot(): void
    {
        $this->post(route('packages.configurations.store'), $this->input)->assertSessionHasNoErrors();
        $product = Product::sole();
        $version = $product->versions()->sole();
        app(PublishPackageVersion::class)->handle($this->admin, $version, $version->draft_revision);
        $before = $version->fresh()->snapshot();
        $data = $this->input;
        unset($data['creation_token']);
        $data = [...$data, 'version_id' => $version->id, 'draft_revision' => $version->draft_revision, 'base_price_pesos' => '900.00'];
        $this->put(route('packages.configurations.update', $product), $data)->assertSessionHasNoErrors();
        $this->assertSame($before, $version->fresh()->snapshot());
        $this->assertSame($version->id, $product->fresh()->current_version_id);
        $this->assertSame('90000', (string) $product->versions()->where('status', 'draft')->sole()->base_price_minor);
        $this->put(route('packages.configurations.update', $product), $data)->assertSessionHasErrors('version');
        $this->assertSame(2, $product->versions()->count());
    }

    public function test_main_catering_still_rejects_food_premiums_and_staff_writes(): void
    {
        $details = [$this->input['food_item_ids'][0] => ['is_fixed' => true, 'price_adjustment_pesos' => '10.00']];
        $this->post(route('packages.configurations.store'), [...$this->input, 'food_details' => $details])->assertSessionHasErrors('food_details');
        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('code', 'staff')->sole());
        $this->actingAs($staff)->withSession(['access_version' => $staff->access_version])->post(route('packages.configurations.store'), $this->input)->assertForbidden();
        $this->assertSame(0, Product::count());
    }

    public function test_editor_get_writes_nothing_and_details_keep_publication_separate_from_draft(): void
    {
        $this->get(route('packages.editor', ['service' => $this->input['family_id']]))->assertOk();
        $this->assertSame(0, Product::count());
        $this->post(route('packages.configurations.store'), $this->input)->assertSessionHasNoErrors();
        $product = Product::sole();
        $version = $product->versions()->sole();
        app(PublishPackageVersion::class)->handle($this->admin, $version, $version->draft_revision);
        $data = $this->input;
        unset($data['creation_token']);
        $this->put(route('packages.configurations.update', $product), [...$data, 'version_id' => $version->id, 'draft_revision' => $version->draft_revision, 'name' => 'New Draft Name', 'base_price_pesos' => '950.00'])->assertSessionHasNoErrors();
        $this->getJson(route('packages.details', $product))->assertOk()->assertJsonPath('status', 'published')->assertJsonPath('snapshot.product.name', 'Package B')->assertJsonPath('snapshot.version.base_price_minor', '85000');
        $this->getJson(route('packages.details', [$product, 'draft' => 1]))->assertOk()->assertJsonPath('status', 'draft')->assertJsonPath('snapshot.product.name', 'New Draft Name')->assertJsonPath('snapshot.version.base_price_minor', '95000');
        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('code', 'staff')->sole());
        $staff->permissionOverrides()->attach(Permission::where('code', 'package.view')->sole(), ['effect' => 'allow']);
        $this->actingAs($staff)->withSession(['access_version' => $staff->access_version]);
        $this->getJson(route('packages.details', $product))->assertOk()->assertJsonPath('status', 'published');
        $this->getJson(route('packages.details', [$product, 'draft' => 1]))->assertForbidden();
    }

    public function test_editor_permissions_are_reflected_in_navigation_and_details(): void
    {
        $this->post(route('packages.configurations.store'), $this->input)->assertSessionHasNoErrors();
        $product = Product::sole();
        $version = $product->versions()->sole();
        app(PublishPackageVersion::class)->handle($this->admin, $version, $version->draft_revision);
        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('code', 'staff')->sole());
        $staff->permissionOverrides()->attach(Permission::where('code', 'package.manage')->sole(), ['effect' => 'allow']);
        $this->actingAs($staff)->withSession(['access_version' => $staff->access_version]);
        $this->get(route('catering.index'))->assertOk()->assertInertia(fn ($page) => $page
            ->where('canManage', true)->where('canPrice', false)->where('canHistory', false));
        $this->get(route('packages.editor', ['service' => $this->input['family_id']]))->assertForbidden();
        $this->get('/admin/packages/products/'.$product->id.'/edit')->assertForbidden();
        $this->getJson(route('packages.details', $product))->assertOk()->assertJsonPath('can_edit', false);
    }

    public function test_generic_package_supports_packed_mode_without_name_based_behavior(): void
    {
        $family = app(CreateCollection::class)->handle($this->admin, ['kind' => 'addon', 'behavior_type' => 'package', 'name' => 'Any Meal Name', 'active' => true]);
        $this->post(route('packages.configurations.store'), [...$this->input, 'family_id' => $family->id, 'price_basis' => 'per_pack', 'selection_policy' => 'split', 'minimum_quantity' => 50])->assertSessionHasNoErrors();
        $version = PackageVersion::sole();
        $this->assertTrue($version->behavior()->supportsSplit());
        $this->assertSame('per_pack', $version->behavior()->priceBasis());
        $family->forceFill(['name' => 'Renamed Lunch Service'])->save();
        app(PublishPackageVersion::class)->handle($this->admin, $version, $version->draft_revision);
        $this->assertSame('split', $version->fresh()->published_snapshot['version']['selection_policy']);
    }

    public function test_food_usage_only_exposes_versions_permitted_to_the_current_user(): void
    {
        $this->post(route('packages.configurations.store'), $this->input)->assertSessionHasNoErrors();
        $product = Product::sole();
        $original = $product->versions()->sole();
        app(PublishPackageVersion::class)->handle($this->admin, $original, $original->draft_revision);
        $second = app(ClonePackageDraft::class)->handle($this->admin, $product, $original->fresh());
        app(PublishPackageVersion::class)->handle($this->admin, $second, $second->draft_revision);
        app(ClonePackageDraft::class)->handle($this->admin, $product->fresh(), $second->fresh());
        $foodId = $this->input['food_item_ids'][0];
        $usage = app(FoodUsage::class);
        $this->assertCount(3, $usage->forIds([$foodId])[$foodId]);
        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('code', 'staff')->sole());
        $staff->permissionOverrides()->attach(Permission::where('code', 'package.view')->sole(), ['effect' => 'allow']);
        $this->actingAs($staff);
        $rows = $usage->forIds([$foodId])[$foodId];
        $this->assertCount(1, $rows);
        $this->assertSame($second->id, $rows[0]['version_id']);
        $staff->permissionOverrides()->attach(Permission::where('code', 'package.history.view')->sole(), ['effect' => 'allow']);
        $this->actingAs($staff->fresh());
        $this->assertCount(2, $usage->forIds([$foodId])[$foodId]);
        $staff->permissionOverrides()->attach(Permission::where('code', 'package.manage')->sole(), ['effect' => 'allow']);
        $this->actingAs($staff->fresh());
        $this->assertCount(3, $usage->forIds([$foodId])[$foodId]);
    }
}
