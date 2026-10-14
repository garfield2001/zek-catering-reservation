<?php

namespace Tests\Feature;

use App\Actions\Catalog\CreateCollection;
use App\Actions\Catalog\PublishListingVersion;
use App\Models\AuditEvent;
use App\Models\Dish;
use App\Models\DishCategory;
use App\Models\DishOffering;
use App\Models\IndividualListing;
use App\Models\ListingVersion;
use App\Models\OfferingOption;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Catalog\ListingConfiguration;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class IndividualListingTest extends TestCase
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
        $family = app(CreateCollection::class)->handle($this->admin, ['kind' => 'addon', 'behavior_type' => 'direct_sale', 'name' => 'Tray Kitchen', 'active' => true]);
        $category = DishCategory::create(['code' => 'chicken', 'name' => 'Chicken', 'active' => true]);
        $food = Dish::create(['code' => 'chicken_food', 'name' => 'Roast Chicken', 'category_id' => $category->id, 'active' => true]);
        $food->eligibleFamilies()->attach($family->id, ['active' => true]);
        $this->input = ['family_id' => $family->id, 'dish_id' => $food->id, 'offering_option_id' => null, 'name' => 'Chicken Tray', 'description' => 'Large tray', 'creation_token' => (string) Str::uuid(), 'price_pesos' => '1250.50', 'selling_unit' => 'tray', 'minimum_quantity' => 2, 'quantity_step' => 2, 'maximum_quantity' => 20];
    }

    private function createListing(): IndividualListing
    {
        $this->post(route('listings.store'), $this->input)->assertSessionHasNoErrors();

        return IndividualListing::sole();
    }

    public function test_draft_save_is_idempotent_and_publication_freezes_prices_names_and_quantities(): void
    {
        $listing = $this->createListing();
        $this->assertNull($listing->current_version_id);
        $this->post(route('listings.store'), $this->input)->assertSessionHasNoErrors();
        $this->assertSame(1, ListingVersion::count());
        $version = $listing->versions()->sole();
        $this->post(route('listings.publish', [$listing, $version]), ['draft_revision' => 1, 'confirm' => true])->assertSessionHasNoErrors();
        $version->refresh();
        $this->assertSame('125050', $version->published_snapshot['price_minor']);
        $listing->dish->forceFill(['name' => 'Renamed Food'])->save();
        $listing->family->forceFill(['name' => 'Renamed Service'])->save();
        $this->assertSame('Roast Chicken', $version->fresh()->published_snapshot['food']['name']);
        $this->assertSame('Tray Kitchen', $version->fresh()->published_snapshot['service']['name']);
        $preview = app(ListingConfiguration::class)->preview($listing, $version, 4);
        $this->assertSame('500200', $preview['total_minor']);
        $this->assertSame(1, AuditEvent::where('action', 'listing.published')->count());
        $this->post(route('listings.publish', [$listing, $version]), ['draft_revision' => 1, 'confirm' => true])->assertSessionHasNoErrors();
        $this->assertSame(1, AuditEvent::where('action', 'listing.published')->count());
    }

    public function test_published_edit_clones_higher_draft_and_history_remains_immutable(): void
    {
        $listing = $this->createListing();
        $version = $listing->versions()->sole();
        app(PublishListingVersion::class)->handle($this->admin, $listing, $version, 1);
        $snapshot = $version->fresh()->published_snapshot;
        $data = $this->input;
        unset($data['creation_token']);
        $data = [...$data, 'version_id' => $version->id, 'draft_revision' => 1, 'price_pesos' => '1500.00'];
        $this->put(route('listings.update', $listing), $data)->assertSessionHasNoErrors();
        $draft = $listing->versions()->where('status', 'draft')->sole();
        $this->assertSame(2, $draft->version_number);
        $this->assertSame('150000', $draft->price_minor);
        $this->assertSame($snapshot, $version->fresh()->published_snapshot);
        $this->assertSame($version->id, $listing->fresh()->current_version_id);
        $this->put(route('listings.update', $listing), [...$data, 'version_id' => $draft->id, 'draft_revision' => 1])->assertSessionHasErrors('draft_revision');
    }

    public function test_invalid_sources_prices_and_unauthorized_requests_leave_no_records(): void
    {
        $this->post(route('listings.store'), [...$this->input, 'price_pesos' => ['bad']])->assertSessionHasErrors('price_pesos');
        Dish::sole()->forceFill(['active' => false])->save();
        $this->post(route('listings.store'), $this->input)->assertSessionHasErrors('dish_id');
        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('code', 'staff')->sole());
        $this->actingAs($staff)->withSession(['access_version' => $staff->access_version])->post(route('listings.store'), $this->input)->assertForbidden();
        $this->assertSame(0, IndividualListing::count());
        $this->assertSame(0, ListingVersion::count());
    }

    public function test_publication_confirmation_and_stale_review_are_enforced(): void
    {
        $listing = $this->createListing();
        $version = $listing->versions()->sole();
        $this->post(route('listings.publish', [$listing, $version]), ['draft_revision' => 1])->assertSessionHasErrors('confirm');
        $this->post(route('listings.publish', [$listing, $version]), ['draft_revision' => 2, 'confirm' => true])->assertSessionHasErrors('version');
        $this->assertNull($listing->fresh()->current_version_id);
    }

    public function test_combined_availability_and_archive_preserve_published_history(): void
    {
        $listing = $this->createListing();
        $version = $listing->versions()->sole();
        app(PublishListingVersion::class)->handle($this->admin, $listing, $version, 1);
        $configuration = app(ListingConfiguration::class);
        $this->assertTrue($configuration->availability($listing->fresh())['available']);
        Dish::sole()->eligibleFamilies()->updateExistingPivot($listing->family_id, ['active' => false]);
        $this->assertFalse($configuration->availability($listing->fresh())['available']);
        $this->post(route('listings.availability', $listing), ['operation' => 'disable'])->assertSessionHasNoErrors();
        $this->assertFalse($listing->fresh()->active);
        $this->post(route('listings.availability', $listing), ['operation' => 'archive'])->assertSessionHasErrors('confirm');
        $this->post(route('listings.availability', $listing), ['operation' => 'archive', 'confirm' => true])->assertSessionHasNoErrors();
        $this->assertNotNull($listing->fresh()->archived_at);
        $this->assertSame($version->id, $listing->fresh()->current_version_id);
        $this->assertSame('published', $version->fresh()->status);
    }

    public function test_postgresql_enforces_positive_quantities_ownership_one_draft_and_immutable_publication(): void
    {
        $listing = $this->createListing();
        $version = $listing->versions()->sole();
        $this->rejectSql(fn () => $version->replicate()->forceFill(['version_number' => 2])->save(), '23505');
        $this->rejectSql(fn () => DB::table('listing_versions')->where('id', $version->id)->update(['quantity_step' => 0]));
        $this->rejectSql(fn () => DB::table('individual_listings')->where('id', $listing->id)->update(['current_version_id' => $version->id]));
        app(PublishListingVersion::class)->handle($this->admin, $listing, $version, 1);
        $this->rejectSql(fn () => DB::table('listing_versions')->where('id', $version->id)->update(['price_minor' => 1]));
        $this->rejectSql(fn () => DB::table('listing_versions')->where('id', $version->id)->delete());
        $this->rejectSql(fn () => DB::table('individual_listings')->where('id', $listing->id)->update(['current_version_id' => null]));
    }

    private function rejectSql(callable $write, string $state = '23514'): void
    {
        DB::statement('SAVEPOINT listing_constraint');
        try {
            $write();
            $this->fail('PostgreSQL accepted invalid listing facts.');
        } catch (QueryException $exception) {
            $this->assertSame($state, $exception->errorInfo[0]);
        } finally {
            DB::statement('ROLLBACK TO SAVEPOINT listing_constraint');
        }
    }

    public function test_staff_edit_permission_cannot_bypass_pricing_publication_or_lifecycle(): void
    {
        $listing = $this->createListing();
        $version = $listing->versions()->sole();
        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('code', 'staff')->sole());
        $staff->permissionOverrides()->attach(Permission::where('code', 'listing.manage')->sole(), ['effect' => 'allow']);
        $data = $this->input;
        unset($data['creation_token']);
        $data = [...$data, 'version_id' => $version->id, 'draft_revision' => 1, 'name' => 'Staff Description Edit'];
        $this->actingAs($staff)->withSession(['access_version' => $staff->access_version]);
        $this->put(route('listings.update', $listing), $data)->assertSessionHasNoErrors();
        $this->put(route('listings.update', $listing), [...$data, 'draft_revision' => 2, 'price_pesos' => '1.00'])->assertForbidden();
        $this->post(route('listings.publish', [$listing, $version]), ['draft_revision' => 2, 'confirm' => true])->assertForbidden();
        $this->post(route('listings.availability', $listing), ['operation' => 'archive', 'confirm' => true])->assertForbidden();
        $this->assertSame('125050', $version->fresh()->price_minor);
        $this->assertNull($listing->fresh()->archived_at);
    }

    public function test_sizes_are_independent_listings_and_wrong_owner_option_is_rejected(): void
    {
        $food = Dish::sole();
        $offering = DishOffering::create(['dish_id' => $food->id, 'family_id' => $this->input['family_id'], 'code' => 'tray', 'name' => 'Tray preparation', 'active' => true]);
        $small = OfferingOption::create(['offering_id' => $offering->id, 'option_code' => 'small', 'label' => 'Small', 'unit' => 'tray', 'quantity' => '1.000', 'active' => true]);
        $large = OfferingOption::create(['offering_id' => $offering->id, 'option_code' => 'large', 'label' => 'Large', 'unit' => 'tray', 'quantity' => '2.000', 'active' => true]);
        foreach ([$small, $large] as $index => $option) {
            $this->post(route('listings.store'), [...$this->input, 'creation_token' => (string) Str::uuid(), 'dish_id' => (string) $food->id, 'family_id' => (string) $this->input['family_id'], 'offering_option_id' => (string) $option->id, 'price_pesos' => $index ? '2000.00' : '1000.00'])->assertSessionHasNoErrors();
        }
        $this->assertSame(2, IndividualListing::count());
        $this->assertEquals(['100000', '200000'], ListingVersion::orderBy('id')->pluck('price_minor')->all());
        $otherFood = Dish::create(['category_id' => $food->category_id, 'code' => 'other', 'name' => 'Other Food', 'active' => true]);
        $otherFood->eligibleFamilies()->attach($this->input['family_id'], ['active' => true]);
        $this->post(route('listings.store'), [...$this->input, 'creation_token' => (string) Str::uuid(), 'dish_id' => $otherFood->id, 'offering_option_id' => $small->id])->assertSessionHasErrors('dish_id');
        $this->assertSame(2, IndividualListing::count());
    }
}
