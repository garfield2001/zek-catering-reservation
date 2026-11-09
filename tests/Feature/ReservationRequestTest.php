<?php

namespace Tests\Feature;

use App\Actions\Package\PublishPackageVersion;
use App\Actions\Package\SavePackageMenuConfiguration;
use App\Actions\Package\SavePackageVersion;
use App\Actions\Package\SaveProduct;
use App\Models\AuditEvent;
use App\Models\Dish;
use App\Models\DishCategory;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\ReservationRequest;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\LegacyCollections;
use Tests\TestCase;

class ReservationRequestTest extends TestCase
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

    public function test_public_submission_freezes_a_current_main_catering_selection_and_is_idempotent(): void
    {
        $product = $this->publishedMain();
        $version = $product->currentVersion;
        $foods = $version->snapshot()['menu']['food_items'];
        $groups = [['quantity' => 150, 'selections' => ['food_items' => collect($foods)->pluck('id')->all(), 'food_sizes' => []]]];
        $key = (string) Str::uuid();
        $payload = ['idempotency_key' => $key, 'full_name' => 'Maria Santos', 'mobile_number' => '09171234567', 'email' => 'maria@example.test', 'event_date' => now('Asia/Manila')->addDays(20)->toDateString(), 'event_start_time' => '22:30', 'venue_information' => 'General Santos City', 'event_notes' => 'Tentative.'];
        $session = ['storefront.selection' => ['package:'.$product->id => ['type' => 'package', 'product_id' => $product->id, 'version_id' => $version->id, 'quantity' => 150, 'groups' => $groups]]];

        $this->withSession($session)->post('/reservation-request', $payload)->assertRedirect();
        $request = ReservationRequest::sole();
        $this->assertSame('submitted', $request->status->value);
        $this->assertSame(150, $request->guest_count);
        $this->assertSame('02:30:00', $request->event_end_time);
        $this->assertTrue($request->event_ends_next_day);
        $this->assertSame($version->id, $request->main_package_version_id);
        $this->assertSame('11250000', $request->estimated_subtotal_minor);
        $this->assertCount(1, $request->lines);
        $this->assertSame($version->snapshot(), $request->lines->first()->commercial_snapshot['published_package']);
        $this->assertDatabaseCount('reservation_request_status_events', 1);

        $session = ['access_version' => $this->admin->access_version];
        $this->actingAs($this->admin)->withSession($session)
            ->post("/admin/reservation-requests/{$request->id}/quotations")
            ->assertRedirect("/admin/quotations/{$request->quotation()->first()->id}");
        $quotationId = $request->quotation()->sole()->id;
        $this->actingAs($this->admin)->withSession($session)
            ->post("/admin/reservation-requests/{$request->id}/quotations")
            ->assertRedirect("/admin/quotations/{$quotationId}")
            ->assertSessionHas('status', 'Opened the existing quotation case for this reservation request.');
        $this->assertDatabaseCount('quotations', 1);

        $this->post('/reservation-request', $payload)->assertRedirect();
        $this->assertDatabaseCount('reservation_requests', 1);
    }

    public function test_request_requires_exactly_one_current_main_package_and_staff_transition_permission(): void
    {
        $this->post('/reservation-request', ['idempotency_key' => (string) Str::uuid(), 'full_name' => 'A', 'mobile_number' => '1', 'email' => 'a@example.test', 'event_date' => now('Asia/Manila')->addDay()->toDateString(), 'event_start_time' => '08:00', 'venue_information' => 'Venue'])->assertSessionHasErrors('selection');
        $product = $this->publishedMain();
        $request = ReservationRequest::create([
            'reference' => 'ZEK-TESTSTATUS', 'idempotency_key' => (string) Str::uuid(), 'status' => 'submitted',
            'customer_name' => 'Status Test', 'customer_mobile' => '09170000000', 'customer_email' => 'status@example.test',
            'event_date' => now('Asia/Manila')->addDays(20)->toDateString(), 'event_start_time' => '08:00:00', 'event_end_time' => '12:00:00',
            'timezone' => 'Asia/Manila', 'venue_information' => 'Venue', 'guest_count' => 150,
            'main_package_version_id' => $product->currentVersion->id, 'estimated_subtotal_minor' => 1, 'currency' => 'PHP', 'commercial_snapshot' => ['schema' => 1],
        ]);
        $request->statusEvents()->create(['to_status' => 'submitted']);
        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('code', 'staff')->sole());
        $this->actingAs($staff)->withSession(['access_version' => $staff->access_version])->get('/admin/reservation-requests')->assertForbidden();
        $this->actingAs($staff)->withSession(['access_version' => $staff->access_version])->put("/admin/reservation-requests/{$request->id}/status", ['status' => 'under_review'])->assertForbidden();
        $staff->permissionOverrides()->attach(Permission::where('code', 'reservation_request.manage')->sole(), ['effect' => 'allow']);
        $staff->refresh();
        $this->actingAs($staff)->withSession(['access_version' => $staff->access_version])->get('/admin/reservation-requests')->assertOk();
        $this->actingAs($staff)->withSession(['access_version' => $staff->access_version])->put("/admin/reservation-requests/{$request->id}/status", ['status' => 'under_review'])->assertRedirect();
        $this->assertSame('under_review', $request->fresh()->status->value);
        $this->assertDatabaseHas('audit_events', ['action' => 'reservation_request.status_updated', 'actor_id' => $staff->id, 'subject_id' => $request->id]);
        $this->actingAs($staff)->withSession(['access_version' => $staff->access_version])->put("/admin/reservation-requests/{$request->id}/status", ['status' => 'submitted'])->assertSessionHasErrors('status');
        $this->assertDatabaseCount('reservation_request_status_events', 2);
    }

    public function test_submission_rejects_stale_or_duplicate_main_selections_and_keeps_acknowledgments_private(): void
    {
        $product = $this->publishedMain();
        $version = $product->currentVersion;
        $groups = [['quantity' => 150, 'selections' => ['food_items' => collect($version->snapshot()['menu']['food_items'])->pluck('id')->all(), 'food_sizes' => []]]];
        $payload = ['idempotency_key' => (string) Str::uuid(), 'full_name' => 'Maria Santos', 'mobile_number' => '09171234567', 'email' => 'maria@example.test', 'event_date' => now('Asia/Manila')->addDays(20)->toDateString(), 'event_start_time' => '10:00', 'venue_information' => 'General Santos City'];

        $stale = ['storefront.selection' => ['package:stale' => ['type' => 'package', 'product_id' => $product->id, 'version_id' => $version->id + 99, 'quantity' => 150, 'groups' => $groups]]];
        $this->withSession($stale)->post('/reservation-request', $payload)->assertSessionHasErrors('selection');

        $duplicate = ['storefront.selection' => [
            'package:first' => ['type' => 'package', 'product_id' => $product->id, 'version_id' => $version->id, 'quantity' => 150, 'groups' => $groups],
            'package:second' => ['type' => 'package', 'product_id' => $product->id, 'version_id' => $version->id, 'quantity' => 150, 'groups' => $groups],
        ]];
        $this->withSession($duplicate)->post('/reservation-request', $payload)->assertSessionHasErrors('selection');

        $payload['idempotency_key'] = (string) Str::uuid();
        $this->withSession(['storefront.selection' => ['package:'.$product->id => $duplicate['storefront.selection']['package:first']]])
            ->post('/reservation-request', $payload)->assertRedirect();
        $request = ReservationRequest::sole();

        $this->flushSession();
        $this->get("/reservation-request/submitted/{$request->reference}")->assertNotFound();
        $this->withSession(['reservation_request.acknowledgment' => $request->reference])
            ->get("/reservation-request/submitted/{$request->reference}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('storefront/reservation-requests/submitted')->where('reference', $request->reference)->missing('customer_name'));
    }

    public function test_submitted_request_lines_and_status_events_are_immutable_and_audited(): void
    {
        $product = $this->publishedMain();
        $version = $product->currentVersion;
        $groups = [['quantity' => 150, 'selections' => ['food_items' => collect($version->snapshot()['menu']['food_items'])->pluck('id')->all(), 'food_sizes' => []]]];
        $this->withSession(['storefront.selection' => ['package:'.$product->id => ['type' => 'package', 'product_id' => $product->id, 'version_id' => $version->id, 'quantity' => 150, 'groups' => $groups]]])
            ->post('/reservation-request', ['idempotency_key' => (string) Str::uuid(), 'full_name' => 'Maria Santos', 'mobile_number' => '09171234567', 'email' => 'maria@example.test', 'event_date' => now('Asia/Manila')->addDays(20)->toDateString(), 'event_start_time' => '10:00', 'venue_information' => 'General Santos City'])
            ->assertRedirect();
        $request = ReservationRequest::with(['lines', 'statusEvents'])->sole();
        $this->assertDatabaseHas('audit_events', ['action' => 'reservation_request.submitted', 'subject_id' => $request->id]);
        $auditData = AuditEvent::where('action', 'reservation_request.submitted')->sole()->after_data;
        $this->assertCount(3, $auditData);
        $this->assertSame($request->reference, $auditData['reference']);
        $this->assertSame('submitted', $auditData['status']);
        $this->assertSame('11250000', $auditData['estimated_subtotal_minor']);

        foreach ([
            fn () => DB::table('reservation_request_lines')->whereKey($request->lines->sole()->id)->update(['quantity' => 1]),
            fn () => DB::table('reservation_request_status_events')->whereKey($request->statusEvents->sole()->id)->update(['to_status' => 'closed']),
        ] as $operation) {
            try {
                $operation();
                $this->fail('Submitted reservation request history must be immutable.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_main_package_replacement_requires_confirmation_and_preserves_one_main_choice(): void
    {
        $first = $this->publishedMain('First Main Catering');
        $second = $this->publishedMain('Second Main Catering');
        $secondFoods = collect($second->currentVersion->snapshot()['menu']['food_items'])->pluck('id')->all();
        $groups = [['quantity' => 150, 'selections' => ['food_items' => $secondFoods, 'food_sizes' => []]]];
        $session = ['storefront.selection' => ['package:'.$first->id => ['type' => 'package', 'product_id' => $first->id, 'version_id' => $first->currentVersion->id, 'quantity' => 150, 'groups' => [['quantity' => 150, 'selections' => ['food_items' => collect($first->currentVersion->snapshot()['menu']['food_items'])->pluck('id')->all(), 'food_sizes' => []]]]]]];

        $this->withSession($session)->postJson("/catering-packages/{$second->id}/selection", ['quantity' => 150, 'groups' => $groups])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('main_package');
        $this->postJson("/catering-packages/{$second->id}/selection", ['quantity' => 150, 'groups' => $groups, 'replace_main' => true])
            ->assertOk()
            ->assertJsonPath('selection_count', 1);
        $this->get('/my-selection')->assertInertia(fn ($page) => $page
            ->where('items.0.offering.id', $second->id)
            ->where('items.0.inquiry_role', 'main'));

        $this->delete('/inquiry')->assertRedirect('/catering-packages');
        $this->get('/my-selection')->assertInertia(fn ($page) => $page
            ->where('items', [])
            ->where('selectionCount', 0));
    }

    public function test_preparing_a_quotation_does_not_create_a_case_until_staff_explicitly_saves_the_draft(): void
    {
        [$request] = $this->submitPublicRequest($this->publishedMain());
        $session = ['access_version' => $this->admin->access_version];

        $this->actingAs($this->admin)->withSession($session)
            ->get("/admin/reservation-requests/{$request->id}/prepare-quotation")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('admin/quotations/prepare'));
        $this->assertDatabaseCount('quotations', 0);
        $this->actingAs($this->admin)->withSession($session)
            ->post("/admin/reservation-requests/{$request->id}/quotations")
            ->assertRedirect();
        $this->assertDatabaseCount('quotations', 1);
        $this->actingAs($this->admin)->withSession($session)
            ->get("/admin/reservation-requests/{$request->id}/prepare-quotation")
            ->assertRedirect("/admin/quotations/{$request->quotation()->sole()->id}");
    }

    public function test_access_code_is_hashed_and_grants_limited_progress_access_for_the_matching_request(): void
    {
        [$request, $code] = $this->submitPublicRequest($this->publishedMain());

        $this->assertNotNull($request->customer_access_code_hash);
        $this->assertNotSame($code, $request->customer_access_code_hash);
        $this->post('/track-request/access', [
            'reference' => $request->reference,
            'mobile_number' => '09171234567',
            'access_code' => 'WRONG-CODE',
        ])->assertSessionHasErrors('access');
        $this->post('/track-request/access', [
            'reference' => $request->reference,
            'mobile_number' => '09171234567',
            'access_code' => $code,
        ])->assertRedirect("/track-request/{$request->reference}");
        $this->get("/track-request/{$request->reference}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('storefront/customer-progress/show')->where('request.reference', $request->reference));
    }

    public function test_authorized_staff_can_reset_an_access_code_and_the_old_code_stops_working(): void
    {
        [$request, $oldCode] = $this->submitPublicRequest($this->publishedMain());
        $session = ['access_version' => $this->admin->access_version];
        $this->actingAs($this->admin)->withSession($session)
            ->post("/admin/reservation-requests/{$request->id}/customer-access-code")
            ->assertRedirect();
        $newCode = $this->app['session.store']->get('customer_access_code');

        $this->assertIsString($newCode);
        $this->assertNotSame($oldCode, $newCode);
        $this->post('/track-request/access', ['reference' => $request->reference, 'mobile_number' => '09171234567', 'access_code' => $oldCode])
            ->assertSessionHasErrors('access');
        $this->post('/track-request/access', ['reference' => $request->reference, 'mobile_number' => '09171234567', 'access_code' => $newCode])
            ->assertRedirect();
        $this->assertDatabaseHas('audit_events', ['action' => 'reservation_request.customer_access_code_reset', 'actor_id' => $this->admin->id, 'subject_id' => $request->id]);
    }

    public function test_online_submission_requires_the_current_minimum_advance_notice(): void
    {
        $product = $this->publishedMain();
        $version = $product->currentVersion;
        $groups = [['quantity' => 150, 'selections' => ['food_items' => collect($version->snapshot()['menu']['food_items'])->pluck('id')->all(), 'food_sizes' => []]]];
        $this->withSession(['storefront.selection' => ['package:'.$product->id => ['type' => 'package', 'product_id' => $product->id, 'version_id' => $version->id, 'quantity' => 150, 'groups' => $groups]]])
            ->post('/reservation-request', ['idempotency_key' => (string) Str::uuid(), 'full_name' => 'Maria Santos', 'mobile_number' => '09171234567', 'event_date' => now('Asia/Manila')->addDays(2)->toDateString(), 'event_start_time' => '10:00', 'venue_information' => 'General Santos City'])
            ->assertSessionHasErrors('event_date');
    }

    public function test_a_published_main_catering_package_starts_an_explicit_editable_draft_without_opening_a_missing_draft_page(): void
    {
        $product = $this->publishedMain();
        $published = $product->currentVersion;
        $this->actingAs($this->admin)->withSession(['access_version' => $this->admin->access_version])
            ->post("/admin/catering-packages/{$product->id}/draft")
            ->assertRedirect("/admin/catering-packages/{$product->id}/details");

        $draft = $product->fresh()->versions()->where('status', 'draft')->sole();
        $this->assertSame($published->id, $draft->cloned_from_id);
        $this->assertSame('published', $published->fresh()->status);
        $this->actingAs($this->admin)->withSession(['access_version' => $this->admin->access_version])
            ->get("/admin/catering-packages/{$product->id}/details")->assertOk();
    }

    /** @return array{0: ReservationRequest, 1: string} */
    private function submitPublicRequest(Product $product): array
    {
        $version = $product->currentVersion;
        $groups = [['quantity' => 150, 'selections' => ['food_items' => collect($version->snapshot()['menu']['food_items'])->pluck('id')->all(), 'food_sizes' => []]]];
        $this->withSession(['storefront.selection' => ['package:'.$product->id => ['type' => 'package', 'product_id' => $product->id, 'version_id' => $version->id, 'quantity' => 150, 'groups' => $groups]]])
            ->post('/reservation-request', ['idempotency_key' => (string) Str::uuid(), 'full_name' => 'Maria Santos', 'mobile_number' => '09171234567', 'email' => null, 'event_date' => now('Asia/Manila')->addDays(20)->toDateString(), 'event_start_time' => '10:00', 'venue_information' => 'General Santos City'])
            ->assertRedirect();
        $code = $this->app['session.store']->get('reservation_request.acknowledgment.access_code');

        return [ReservationRequest::latest('id')->firstOrFail(), $code];
    }

    private function publishedMain(string $name = 'Request Main Catering'): Product
    {
        $family = ProductFamily::where('code', 'main_catering')->sole();
        $category = DishCategory::create(['code' => 'request_main_'.Str::lower(Str::random(6)), 'name' => 'Request Main '.Str::lower(Str::random(6)), 'active' => true]);
        $first = Dish::create(['code' => 'request_food_'.Str::lower(Str::random(6)), 'category_id' => $category->id, 'name' => 'Request Rice', 'active' => true]);
        $second = Dish::create(['code' => 'request_food_'.Str::lower(Str::random(6)), 'category_id' => $category->id, 'name' => 'Request Chicken', 'active' => true]);
        $product = app(SaveProduct::class)->handle($this->admin, ['family_id' => $family->id, 'name' => $name, 'description' => null, 'active' => true]);
        $draft = $product->versions()->sole();
        app(SavePackageVersion::class)->handle($this->admin, $product, ['base_price_pesos' => '750.00', 'selection_policy' => 'uniform', 'minimum_quantity' => 150, 'quantity_step' => 1, 'maximum_quantity' => null, 'total_selection_count' => 2, 'draft_revision' => $draft->draft_revision], $draft);
        $draft->refresh();
        app(SavePackageMenuConfiguration::class)->handle($this->admin, $draft, ['draft_revision' => $draft->draft_revision, 'total_selection_count' => 2, 'food_item_ids' => [$first->id, $second->id], 'required_category_ids' => []]);
        $draft->refresh();
        app(PublishPackageVersion::class)->handle($this->admin, $draft, $draft->draft_revision);

        return $product->fresh(['currentVersion']);
    }
}
