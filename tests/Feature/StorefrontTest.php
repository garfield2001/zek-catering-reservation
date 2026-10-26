<?php

namespace Tests\Feature;

use App\Actions\Catalog\PublishListingVersion;
use App\Actions\Catalog\SaveIndividualListing;
use App\Actions\Package\PublishPackageVersion;
use App\Actions\Package\SavePackageMenuConfiguration;
use App\Actions\Package\SavePackageVersion;
use App\Actions\Package\SaveProduct;
use App\Actions\Package\SaveSelectionGroup;
use App\Models\Dish;
use App\Models\DishCategory;
use App\Models\DishOffering;
use App\Models\OfferingOption;
use App\Models\PackageVersion;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\LegacyCollections;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        LegacyCollections::seed();
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::where('code', 'admin')->sole());
    }

    public function test_public_homepage_never_advertises_internal_login_and_internal_login_remains_directly_available(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('storefront/home'))
            ->assertDontSee('Admin Login')
            ->assertDontSee('Staff Login')
            ->assertDontSee('Sign In');
        $this->get('/login')->assertOk()->assertInertia(fn ($page) => $page->component('auth/login'));
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_admin_and_staff_can_browse_the_public_homepage_without_a_dashboard_redirect(): void
    {
        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('code', 'staff')->sole());
        foreach ([$this->admin, $staff] as $user) {
            $this->actingAs($user)->withSession(['access_version' => $user->access_version])
                ->get('/')->assertOk()->assertInertia(fn ($page) => $page->component('storefront/home'));
        }
    }

    public function test_only_current_available_publication_can_be_viewed_previewed_and_saved_in_the_browser_selection(): void
    {
        $product = $this->publishedPackage();
        $option = $product->currentVersion->snapshot()['groups'][0]['options'][0]['offering_option_id'];
        $this->get('/catering-packages/'.$product->id)->assertNotFound();
        $family = $product->family;
        $this->get('/add-on-services/'.$family->id)->assertOk()->assertSee($product->name);
        $payload = ['quantity' => 1, 'groups' => [['quantity' => 1, 'selections' => ['choice' => [$option]]]]];
        $this->postJson("/add-on-services/{$family->id}/packages/{$product->id}/preview", $payload)
            ->assertOk()->assertJsonPath('status', 'published_preview')->assertJsonPath('total_minor', '45000');
        $this->postJson("/add-on-services/{$family->id}/packages/{$product->id}/preview", [...$payload, 'quantity' => '1'])
            ->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->postJson("/add-on-services/{$family->id}/packages/{$product->id}/selection", $payload)
            ->assertOk()->assertJsonPath('selection_count', 1);
        $this->get('/my-selection')->assertOk()->assertInertia(fn ($page) => $page
            ->component('storefront/selection/index')
            ->where('items.0.offering.name', $product->name)
            ->where('totalMinor', '45000'));
        $product->update(['active' => false]);
        $this->postJson("/add-on-services/{$family->id}/packages/{$product->id}/preview", $payload)->assertNotFound();
        $this->get('/my-selection')->assertOk()->assertSee('no longer available');
    }

    public function test_draft_customer_presentation_is_internal_only(): void
    {
        $family = ProductFamily::where('code', 'belly_lechon')->sole();
        $product = app(SaveProduct::class)->handle($this->admin, ['family_id' => $family->id, 'name' => 'Unpublished', 'description' => null, 'active' => true]);
        $draft = $product->versions()->sole();
        $this->get("/add-on-services/{$family->id}")->assertDontSee('Unpublished');
        $this->get("/admin/packages/products/{$product->id}/versions/{$draft->id}/customer-preview")->assertRedirect('/login');
        $this->actingAs($this->admin)->withSession(['access_version' => $this->admin->access_version])
            ->get("/admin/packages/products/{$product->id}/versions/{$draft->id}/customer-preview")
            ->assertOk()->assertInertia(fn ($page) => $page->component('admin/packages/storefront-preview'));
    }

    public function test_published_individual_items_use_their_frozen_listing_price_and_quantity_rules(): void
    {
        $family = ProductFamily::where('code', 'food_tray')->sole();
        $category = DishCategory::create(['code' => 'storefront_tray', 'name' => 'Storefront Trays', 'active' => true]);
        $dish = Dish::create(['code' => 'storefront_noodles', 'category_id' => $category->id, 'name' => 'Storefront Noodles', 'active' => true]);
        $listing = app(SaveIndividualListing::class)->handle($this->admin, [
            'family_id' => $family->id, 'dish_id' => $dish->id, 'offering_option_id' => null,
            'creation_token' => (string) Str::uuid(), 'name' => 'Noodle Tray', 'description' => null,
            'price_pesos' => '900.00', 'selling_unit' => 'tray', 'minimum_quantity' => 2,
            'quantity_step' => 2, 'maximum_quantity' => null,
        ]);
        $version = $listing->versions()->sole();
        app(PublishListingVersion::class)->handle($this->admin, $listing, $version, $version->draft_revision);
        $this->get("/add-on-services/{$family->id}")->assertOk()->assertSee('Noodle Tray');
        $this->postJson("/add-on-services/{$family->id}/foods/{$listing->id}/selection", ['quantity' => 4])
            ->assertOk()->assertJsonPath('preview.total_minor', '360000');
        $this->postJson("/add-on-services/{$family->id}/foods/{$listing->id}/selection", ['quantity' => 3])
            ->assertUnprocessable()->assertJsonValidationErrors('quantity');
    }

    public function test_published_main_catering_menu_is_publicly_configurable_only_with_the_frozen_menu_rules(): void
    {
        $family = ProductFamily::where('code', 'main_catering')->sole();
        $category = DishCategory::create(['code' => 'storefront_main', 'name' => 'Storefront Main', 'active' => true]);
        $first = Dish::create(['code' => 'storefront_rice', 'category_id' => $category->id, 'name' => 'Storefront Rice', 'active' => true]);
        $second = Dish::create(['code' => 'storefront_chicken', 'category_id' => $category->id, 'name' => 'Storefront Chicken', 'active' => true]);
        $product = app(SaveProduct::class)->handle($this->admin, ['family_id' => $family->id, 'name' => 'Public Catering', 'description' => null, 'active' => true]);
        $draft = $product->versions()->sole();
        app(SavePackageVersion::class)->handle($this->admin, $product, ['base_price_pesos' => '750.00', 'selection_policy' => 'uniform', 'minimum_quantity' => 150, 'quantity_step' => 1, 'maximum_quantity' => null, 'total_selection_count' => 2, 'draft_revision' => $draft->draft_revision], $draft);
        $draft->refresh();
        app(SavePackageMenuConfiguration::class)->handle($this->admin, $draft, ['draft_revision' => $draft->draft_revision, 'total_selection_count' => 2, 'food_item_ids' => [$first->id, $second->id], 'required_category_ids' => []]);
        $draft->refresh();
        app(PublishPackageVersion::class)->handle($this->admin, $draft, $draft->draft_revision);
        $this->get('/catering-packages')->assertOk()->assertSee('Public Catering');
        $payload = ['quantity' => 150, 'groups' => [['quantity' => 150, 'selections' => ['food_items' => [$first->id, $second->id], 'food_sizes' => []]]]];
        $this->postJson("/catering-packages/{$product->id}/preview", $payload)
            ->assertOk()->assertJsonPath('status', 'published_preview')->assertJsonPath('total_minor', '11250000');
        $this->postJson("/catering-packages/{$product->id}/preview", [...$payload, 'quantity' => 149])
            ->assertUnprocessable()->assertJsonValidationErrors('quantity');
    }

    private function publishedPackage(): Product
    {
        $family = ProductFamily::where('code', 'belly_lechon')->sole();
        $category = DishCategory::create(['code' => 'storefront_meat', 'name' => 'Storefront Meat', 'active' => true]);
        $dish = Dish::create(['code' => 'storefront_lechon', 'category_id' => $category->id, 'name' => 'Storefront Lechon', 'active' => true]);
        $offering = DishOffering::create(['dish_id' => $dish->id, 'family_id' => $family->id, 'code' => 'storefront', 'name' => 'Storefront option', 'active' => true]);
        $option = OfferingOption::create(['offering_id' => $offering->id, 'option_code' => 'whole', 'label' => 'Whole tray', 'unit' => 'tray', 'quantity' => '1.000', 'active' => true]);
        $product = app(SaveProduct::class)->handle($this->admin, ['family_id' => $family->id, 'name' => 'Public Lechon', 'description' => 'A published package', 'active' => true]);
        /** @var PackageVersion $draft */
        $draft = $product->versions()->sole();
        $draft->update(['base_price_minor' => 45000]);
        app(SaveSelectionGroup::class)->handle($this->admin, $draft, ['draft_revision' => $draft->fresh()->draft_revision, 'name' => 'Choice', 'group_code' => 'choice', 'mode' => 'select_one', 'slot_kind' => 'item', 'minimum_count' => 1, 'maximum_count' => 1, 'unique_dishes' => true, 'category_mode' => 'free', 'inclusion_count' => '1.000', 'options' => [['offering_option_id' => $option->id, 'is_default' => true, 'price_adjustment_minor' => '0']], 'category_rules' => [], 'dish_rules' => []]);
        app(PublishPackageVersion::class)->handle($this->admin, $draft->fresh(), $draft->fresh()->draft_revision);

        return $product->fresh(['family', 'currentVersion']);
    }
}
