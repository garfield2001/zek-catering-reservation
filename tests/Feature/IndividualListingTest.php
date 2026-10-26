<?php

namespace Tests\Feature;

use App\Actions\Catalog\CloneListingDraft;
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
use App\Models\ProductFamily;
use App\Models\Role;
use App\Models\User;
use App\Queries\Catalog\CommercialHistory;
use App\Queries\Catalog\ListingWorkspace;
use App\Services\Catalog\ListingConfiguration;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
        $this->input = ['family_id' => $family->id, 'dish_id' => $food->id, 'offering_option_id' => null, 'name' => 'Chicken Tray', 'description' => 'Large tray', 'creation_token' => (string) Str::uuid(), 'price_pesos' => '1250.50', 'selling_unit' => 'tray', 'minimum_quantity' => 2, 'quantity_step' => 2, 'maximum_quantity' => 20];
    }

    private function createListing(): IndividualListing
    {
        $this->post(route('listings.store'), $this->input)->assertSessionHasNoErrors();

        return IndividualListing::sole();
    }

    public function test_configurable_selling_units_preserve_absolute_prices_and_publication_history(): void
    {
        $this->input['selling_unit'] = '  meal box  ';
        $listing = $this->groupedListing();
        $version = $listing->versions()->sole();
        $this->assertSame('meal box', $version->selling_unit);
        app(PublishListingVersion::class)->handle($this->admin, $listing, $version, 1);
        $snapshot = $version->fresh()->snapshot();
        $this->assertSame('meal box', $snapshot['selling_unit']);
        $large = $version->sellingOptions()->where('label', 'Large Tray')->sole();
        $this->assertSame('440000', app(ListingConfiguration::class)->preview($listing, $version, 4, $large->id)['total_minor']);
        $draft = app(CloneListingDraft::class)->handle($this->admin, $listing, $version);
        $this->assertSame('meal box', $draft->selling_unit);
        $data = $this->input;
        unset($data['creation_token']);
        $this->put(route('listings.update', $listing), [...$data, 'selling_unit' => 'serving basket', 'version_id' => $draft->id, 'draft_revision' => 1])->assertSessionHasNoErrors();
        $this->assertSame('serving basket', $draft->fresh()->selling_unit);
        $this->assertSame($snapshot, $version->fresh()->snapshot());
    }

    public function test_invalid_selling_units_are_rejected_by_http_and_database(): void
    {
        foreach ([[], true, '', '   ', str_repeat('x', 31), "meal\nbox"] as $unit) {
            $this->post(route('listings.store'), [...$this->input, 'selling_unit' => $unit])->assertSessionHasErrors('selling_unit');
            $this->assertSame(0, IndividualListing::count());
        }
        $version = $this->createListing()->versions()->sole();
        foreach (['', ' ', ' tray ', "meal\nbox"] as $unit) {
            $this->rejectSql(fn () => DB::table('listing_versions')->where('id', $version->id)->update(['selling_unit' => $unit]));
        }
    }

    private function groupedListing(): IndividualListing
    {
        unset($this->input['price_pesos']);
        $this->input['name'] = '';
        $this->input['selling_options'] = [
            ['label' => 'Small Tray', 'price_pesos' => '650'],
            ['label' => 'Large Tray', 'price_pesos' => '1100'],
        ];

        return $this->createListing();
    }

    public function test_grouped_absolute_prices_and_food_name_are_frozen_and_cloned_without_changing_history(): void
    {
        $listing = $this->groupedListing();
        $version = $listing->versions()->sole();
        $this->assertTrue($version->uses_food_name);
        $this->assertSame('Roast Chicken', $version->name);
        $this->assertSame('65000', $version->price_minor);
        $this->assertCount(2, $version->sellingOptions);
        app(PublishListingVersion::class)->handle($this->admin, $listing, $version, 1);
        $version->refresh();
        $snapshot = $version->snapshot();
        $large = $version->sellingOptions()->where('label', 'Large Tray')->sole();
        $this->assertSame('440000', app(ListingConfiguration::class)->preview($listing, $version, 4, $large->id)['total_minor']);
        foreach ([null, 999999] as $selection) {
            try {
                app(ListingConfiguration::class)->preview($listing, $version, 4, $selection);
                $this->fail('An explicit owned selling option is required.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('selling_option_id', $exception->errors());
            }
        }
        $listing->dish->update(['name' => 'Renamed Food']);
        $draft = app(CloneListingDraft::class)->handle($this->admin, $listing, $version);
        $this->assertCount(2, $draft->sellingOptions);
        $this->assertNotSame($large->id, $draft->sellingOptions()->where('label', 'Large Tray')->sole()->id);
        $data = $this->input;
        unset($data['creation_token']);
        $data['selling_options'][1]['price_pesos'] = '1200';
        $this->put(route('listings.update', $listing), [...$data, 'version_id' => $draft->id, 'draft_revision' => 1])->assertSessionHasNoErrors();
        $this->assertSame('Renamed Food', $draft->fresh()->name);
        $this->assertSame($snapshot, $version->fresh()->snapshot());
        $this->assertSame($version->id, $listing->fresh()->current_version_id);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_legacy_publication_can_be_revised_into_grouped_options_without_repricing_history(): void
    {
        $listing = $this->createListing();
        $published = $listing->versions()->sole();
        app(PublishListingVersion::class)->handle($this->admin, $listing, $published, 1);
        $snapshot = $published->fresh()->snapshot();
        $draft = app(CloneListingDraft::class)->handle($this->admin, $listing, $published);
        $data = $this->input;
        unset($data['creation_token'], $data['price_pesos']);
        $this->put(route('listings.update', $listing), [...$data,
            'version_id' => $draft->id, 'draft_revision' => 1,
            'selling_options' => [['label' => 'Small', 'price_pesos' => '650'], ['label' => 'Large', 'price_pesos' => '1100']],
        ])->assertSessionHasNoErrors();
        $this->assertTrue($draft->fresh()->has_selling_options);
        $this->assertCount(2, $draft->fresh()->sellingOptions);
        $this->assertSame($snapshot, $published->fresh()->snapshot());
        $this->assertSame('500200', app(ListingConfiguration::class)->preview($listing, $published, 4)['total_minor']);
        $this->assertSame($published->id, $listing->fresh()->current_version_id);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_grouped_option_validation_is_atomic_and_rejects_malformed_or_duplicate_labels(): void
    {
        foreach ([[], [['label' => ['bad'], 'price_pesos' => '1']], [['label' => 'Small', 'price_pesos' => ['bad']]],
            [['label' => 'Small', 'price_pesos' => '1'], ['label' => ' small ', 'price_pesos' => '2']],
            [['label' => 'Small', 'price_pesos' => '-1']]] as $options) {
            $response = $this->post(route('listings.store'), [...$this->input, 'selling_options' => $options]);
            $response->assertSessionHasErrors();
            $this->assertSame(0, IndividualListing::count());
        }
    }

    public function test_commercial_history_uses_recorded_actors_and_frozen_prices_and_is_permission_scoped(): void
    {
        $listing = $this->groupedListing();
        $first = $listing->versions()->sole();
        $actorLabel = $this->admin->email;
        app(PublishListingVersion::class)->handle($this->admin, $listing, $first, 1);
        $draft = app(CloneListingDraft::class)->handle($this->admin, $listing, $first);
        $data = $this->input;
        unset($data['creation_token']);
        $data['selling_options'][1]['price_pesos'] = '1200';
        $this->put(route('listings.update', $listing), [...$data, 'version_id' => $draft->id, 'draft_revision' => 1])->assertSessionHasNoErrors();
        app(PublishListingVersion::class)->handle($this->admin, $listing, $draft, 2);
        $this->admin->update(['email' => 'renamed-history@example.test']);
        $history = app(CommercialHistory::class)->forOwner($listing);
        $this->assertSame($actorLabel, $history['publications'][1]['publisher']);
        $this->assertSame(1, $history['publications'][1]['compared_to']);
        $this->assertContains(['label' => 'Selling option: Large Tray', 'before' => 'PHP 1100.00', 'after' => 'PHP 1200.00'], $history['publications'][1]['changes']);
        $this->assertNotNull($history['publications'][1]['published_at']);
        $this->assertTrue($history['events']->contains('action', 'listing.draft_updated'));
        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('code', 'staff')->sole());
        $staff->permissionOverrides()->attach(Permission::where('code', 'listing.view')->sole(), ['effect' => 'allow']);
        $this->actingAs($staff)->withSession(['access_version' => $staff->access_version]);
        $this->assertNull(app(ListingWorkspace::class)->details($listing->fresh())['commercialHistory']);
    }

    public function test_staff_can_retain_options_but_cannot_change_a_nonminimum_option_price(): void
    {
        $listing = $this->groupedListing();
        $version = $listing->versions()->sole();
        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('code', 'staff')->sole());
        $staff->permissionOverrides()->attach(Permission::where('code', 'listing.manage')->sole(), ['effect' => 'allow']);
        $this->actingAs($staff)->withSession(['access_version' => $staff->access_version]);
        $data = $this->input;
        unset($data['creation_token']);
        $data = [...$data, 'version_id' => $version->id, 'draft_revision' => 1, 'description' => 'Staff presentation edit'];
        $this->put(route('listings.update', $listing), $data)->assertSessionHasNoErrors();
        $data['draft_revision'] = 2;
        $data['selling_options'][1]['price_pesos'] = '1200';
        $this->put(route('listings.update', $listing), $data)->assertForbidden();
        $this->assertSame('110000', $version->sellingOptions()->where('label', 'Large Tray')->sole()->price_minor);
    }

    public function test_postgresql_rejects_duplicate_empty_mispriced_and_published_option_mutations(): void
    {
        $listing = $this->groupedListing();
        $version = $listing->versions()->sole();
        $option = $version->sellingOptions()->firstOrFail();
        foreach ([
            fn () => $version->sellingOptions()->create(['label' => ' small tray ', 'price_minor' => 1, 'sort_order' => 3]),
            fn () => $version->sellingOptions()->delete(),
            fn () => $option->update(['price_minor' => 1]),
        ] as $mutation) {
            DB::beginTransaction();
            try {
                $mutation();
                DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
                $this->fail('Expected PostgreSQL integrity rejection.');
            } catch (QueryException $exception) {
                $this->assertContains($exception->errorInfo[0], ['23514', '23505']);
            } finally {
                DB::rollBack();
            }
        }
        app(PublishListingVersion::class)->handle($this->admin, $listing, $version, 1);
        $this->expectException(QueryException::class);
        $option->update(['label' => 'Changed publication']);
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

    public function test_bulk_creation_uses_active_service_eligibility_is_atomic_and_can_be_safely_retried(): void
    {
        $family = ProductFamily::findOrFail($this->input['family_id']);
        $first = Dish::sole();
        $second = Dish::create([
            'code' => 'second_chicken_food',
            'name' => 'Garlic Chicken',
            'category_id' => $first->category_id,
            'active' => true,
        ]);
        $ineligible = Dish::create([
            'code' => 'ineligible_food',
            'name' => 'Unavailable Food',
            'category_id' => $first->category_id,
            'active' => true,
        ]);
        $payload = ['listings' => [
            ['dish_id' => $first->id, 'creation_token' => (string) Str::uuid(), 'selling_unit' => 'tray', 'price_pesos' => '750', 'minimum_quantity' => 1, 'quantity_step' => 1, 'maximum_quantity' => null],
            ['dish_id' => $second->id, 'creation_token' => (string) Str::uuid(), 'selling_unit' => 'tray', 'price_pesos' => '800', 'minimum_quantity' => 2, 'quantity_step' => 1, 'maximum_quantity' => 10],
        ]];

        $this->post(route('listings.bulk.store', $family), $payload)->assertSessionHasNoErrors();
        $this->assertSame(2, IndividualListing::count());
        $this->post(route('listings.bulk.store', $family), $payload)->assertSessionHasNoErrors();
        $this->assertSame(2, IndividualListing::count());

        $this->post(route('listings.bulk.store', $family), ['listings' => [[
            ...$payload['listings'][0], 'creation_token' => (string) Str::uuid(),
        ]]])->assertSessionHasErrors('listings.0.dish_id');
        $ineligible->update(['active' => false]);
        $this->post(route('listings.bulk.store', $family), ['listings' => [[
            ...$payload['listings'][0], 'dish_id' => $ineligible->id, 'creation_token' => (string) Str::uuid(),
        ]]])->assertSessionHasErrors('listings.0.dish_id');
        $this->assertSame(2, IndividualListing::count());
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
        $frozen = $version->fresh()->snapshot();
        $this->getJson(route('catalog.deactivation-impact', ['food', $listing->dish_id]))
            ->assertOk()->assertJsonPath('usage.0.product_id', $listing->id);
        $configuration = app(ListingConfiguration::class);
        $this->assertTrue($configuration->availability($listing->fresh())['available']);
        Dish::sole()->update(['active' => false]);
        $this->assertFalse($configuration->availability($listing->fresh())['available']);
        $this->post(route('listings.availability', $listing), ['operation' => 'disable'])->assertSessionHasNoErrors();
        $this->assertFalse($listing->fresh()->active);
        $this->post(route('listings.availability', $listing), ['operation' => 'archive'])->assertSessionHasErrors('confirm');
        $this->post(route('listings.availability', $listing), ['operation' => 'archive', 'confirm' => true])->assertSessionHasNoErrors();
        $this->assertNotNull($listing->fresh()->archived_at);
        $this->assertSame($version->id, $listing->fresh()->current_version_id);
        $this->assertSame('published', $version->fresh()->status);
        $this->assertSame($frozen, $version->fresh()->snapshot());
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
        $this->put(route('listings.update', $listing), [...$data, 'draft_revision' => 2, 'selling_unit' => 'meal box'])->assertForbidden();
        $this->assertSame('tray', $version->fresh()->selling_unit);
        $this->post(route('listings.publish', [$listing, $version]), ['draft_revision' => 2, 'confirm' => true])->assertForbidden();
        $this->post(route('listings.availability', $listing), ['operation' => 'archive', 'confirm' => true])->assertForbidden();
        $this->assertSame('125050', $version->fresh()->price_minor);
        $this->assertNull($listing->fresh()->archived_at);
    }

    public function test_legacy_separate_size_listings_remain_valid_and_wrong_owner_option_is_rejected(): void
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
        $this->post(route('listings.store'), [...$this->input, 'creation_token' => (string) Str::uuid(), 'dish_id' => $otherFood->id, 'offering_option_id' => $small->id])->assertSessionHasErrors('dish_id');
        $this->assertSame(2, IndividualListing::count());
    }
}
