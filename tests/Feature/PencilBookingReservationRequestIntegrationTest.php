<?php

namespace Tests\Feature;

use App\Actions\Package\PublishPackageVersion;
use App\Actions\Package\SavePackageMenuConfiguration;
use App\Actions\Package\SavePackageVersion;
use App\Actions\Package\SaveProduct;
use App\Models\Dish;
use App\Models\DishCategory;
use App\Models\PencilBooking;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\ReservationRequest;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\LegacyCollections;
use Tests\TestCase;

class PencilBookingReservationRequestIntegrationTest extends TestCase
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

    public function test_authorized_staff_can_create_an_internal_request_with_the_public_submission_engine(): void
    {
        $product = $this->publishedMain();
        $payload = $this->payload($product);

        $this->actingAs($this->admin)->withSession(['access_version' => $this->admin->access_version])
            ->post('/admin/reservation-requests', $payload)
            ->assertRedirect();

        $request = ReservationRequest::with(['lines', 'statusEvents'])->sole();
        $this->assertSame('manual', $request->origin);
        $this->assertSame($this->admin->id, $request->created_by);
        $this->assertSame($product->currentVersion->id, $request->main_package_version_id);
        $this->assertSame('11250000', $request->estimated_subtotal_minor);
        $this->assertSame($this->admin->id, $request->statusEvents->sole()->changed_by);
        $this->assertSame('manual', $request->commercial_snapshot['origin']);
        $this->assertDatabaseHas('audit_events', ['action' => 'reservation_request.submitted', 'actor_id' => $this->admin->id, 'subject_id' => $request->id]);

        $this->actingAs($this->admin)->withSession(['access_version' => $this->admin->access_version])
            ->post('/admin/reservation-requests', $payload)
            ->assertRedirect();
        $this->assertDatabaseCount('reservation_requests', 1);
    }

    public function test_internal_creation_and_conversion_pages_expose_only_published_catalog_facts_to_authorized_users(): void
    {
        $product = $this->publishedMain();
        $booking = $this->booking();
        $session = ['access_version' => $this->admin->access_version];

        $this->actingAs($this->admin)->withSession($session)
            ->get('/admin/reservation-requests/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/reservation-requests/create')
                ->where('catalog.main_packages.0.id', $product->id)
                ->where('catalog.main_packages.0.version_id', $product->currentVersion->id));

        $this->actingAs($this->admin)->withSession($session)
            ->get("/admin/pencil-bookings/{$booking->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/pencil-bookings/show')
                ->where('canConvert', true)
                ->where('booking.event_date', $booking->event_date->toDateString())
                ->where('booking.event_start_time', '10:00')
                ->where('catalog.main_packages.0.id', $product->id));
    }

    public function test_internal_request_requires_complete_details_and_rejects_stale_selections(): void
    {
        $product = $this->publishedMain();
        $session = ['access_version' => $this->admin->access_version];
        $this->actingAs($this->admin)->withSession($session)
            ->post('/admin/reservation-requests', ['idempotency_key' => (string) Str::uuid()])
            ->assertSessionHasErrors(['full_name', 'mobile_number', 'event_date', 'selections']);

        $payload = $this->payload($product);
        $payload['selections'][0]['version_id']++;
        $this->actingAs($this->admin)->withSession($session)
            ->post('/admin/reservation-requests', $payload)
            ->assertSessionHasErrors('selection');
        $this->assertDatabaseCount('reservation_requests', 0);
    }

    public function test_internal_request_rejects_duplicate_package_lines_from_a_manipulated_payload(): void
    {
        $product = $this->publishedMain();
        $payload = $this->payload($product);
        $payload['selections'][] = $this->selection($product);

        $this->actingAs($this->admin)->withSession(['access_version' => $this->admin->access_version])
            ->post('/admin/reservation-requests', $payload)
            ->assertSessionHasErrors('selection');

        $this->assertDatabaseCount('reservation_requests', 0);
    }

    public function test_authorized_staff_can_create_a_normal_notice_request_but_cannot_forge_an_emergency_exception(): void
    {
        $product = $this->publishedMain();
        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('code', 'staff')->sole());
        $staff->permissionOverrides()->attach(Permission::where('code', 'reservation_request.manage')->sole(), ['effect' => 'allow']);
        $session = ['access_version' => $staff->access_version];

        $this->actingAs($staff)->withSession($session)->post('/admin/reservation-requests', $this->payload($product))->assertRedirect();
        $this->assertSame('manual', ReservationRequest::sole()->origin);

        $emergency = $this->payload($product, [
            'idempotency_key' => (string) Str::uuid(),
            'event_date' => now('Asia/Manila')->addDay()->toDateString(),
            'emergency_booking_exception' => true,
            'emergency_booking_reason' => 'Forged bypass.',
        ]);
        $this->actingAs($staff)->withSession($session)->post('/admin/reservation-requests', $emergency)
            ->assertSessionHasErrors('emergency_booking_exception');
        $this->assertDatabaseCount('reservation_requests', 1);
    }

    public function test_pencil_booking_conversion_requires_an_administrator_reason_for_short_notice(): void
    {
        $product = $this->publishedMain();
        $booking = $this->booking();
        $booking->update(['event_date' => now('Asia/Manila')->addDay()->toDateString()]);
        $booking->refresh();
        $session = ['access_version' => $this->admin->access_version];
        $withoutException = $this->payload($product, ['event_date' => $booking->event_date->toDateString()]);
        $this->actingAs($this->admin)->withSession($session)->post("/admin/pencil-bookings/{$booking->id}/convert", $withoutException)
            ->assertSessionHasErrors('event_date');

        $withException = $this->payload($product, [
            'idempotency_key' => (string) Str::uuid(),
            'event_date' => $booking->event_date->toDateString(),
            'emergency_booking_exception' => true,
            'emergency_booking_reason' => 'Approved short-notice conversion.',
        ]);
        $this->actingAs($this->admin)->withSession($session)->post("/admin/pencil-bookings/{$booking->id}/convert", $withException)->assertRedirect();
        $request = ReservationRequest::sole();
        $this->assertTrue($request->emergency_booking_exception);
        $this->assertSame($booking->id, $request->pencil_booking_id);
        $this->assertNull($request->quotation);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_unauthorized_staff_cannot_create_or_convert_reservation_requests(): void
    {
        $product = $this->publishedMain();
        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('code', 'staff')->sole());
        $booking = $this->booking();

        $this->actingAs($staff)->withSession(['access_version' => $staff->access_version])
            ->post('/admin/reservation-requests', $this->payload($product))
            ->assertForbidden();
        $this->actingAs($staff)->withSession(['access_version' => $staff->access_version])
            ->post("/admin/pencil-bookings/{$booking->id}/convert", $this->payload($product))
            ->assertForbidden();

        $staff->permissionOverrides()->attach(Permission::where('code', 'reservation_request.manage')->sole(), ['effect' => 'allow']);
        $staff->refresh();
        $this->actingAs($staff)->withSession(['access_version' => $staff->access_version])
            ->post("/admin/pencil-bookings/{$booking->id}/convert", $this->payload($product))
            ->assertForbidden();

        $pencilStaff = User::factory()->create();
        $pencilStaff->roles()->attach(Role::where('code', 'staff')->sole());
        $pencilStaff->permissionOverrides()->attach(Permission::where('code', 'pencil_booking.manage')->sole(), ['effect' => 'allow']);
        $pencilStaff->refresh();
        $this->actingAs($pencilStaff)->withSession(['access_version' => $pencilStaff->access_version])
            ->get("/admin/pencil-bookings/{$booking->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('canManage', true)
                ->where('canCreateRequest', false)
                ->where('canConvert', false)
                ->where('catalog', null));

    }

    public function test_pencil_booking_conversion_is_idempotent_and_preserves_provenance_history(): void
    {
        $product = $this->publishedMain();
        $booking = $this->booking();
        $payload = $this->payload($product, ['full_name' => $booking->customer_name, 'mobile_number' => $booking->customer_mobile, 'email' => $booking->customer_email, 'event_date' => $booking->event_date->toDateString(), 'event_start_time' => $booking->event_start_time, 'venue_information' => $booking->venue_information]);
        $session = ['access_version' => $this->admin->access_version];

        $this->actingAs($this->admin)->withSession($session)
            ->post("/admin/pencil-bookings/{$booking->id}/convert", $payload)
            ->assertRedirect();
        $request = ReservationRequest::sole();
        $booking->refresh();
        $this->assertSame('pencil_booking', $request->origin);
        $this->assertSame($booking->id, $request->pencil_booking_id);
        $this->assertSame($request->id, $booking->converted_reservation_request_id);
        $this->assertSame('converted', $booking->status);
        $this->assertNotNull($booking->converted_at);
        $this->assertDatabaseHas('pencil_booking_events', ['pencil_booking_id' => $booking->id, 'event_type' => 'converted', 'actor_id' => $this->admin->id]);
        $this->assertDatabaseHas('audit_events', ['action' => 'pencil_booking.converted', 'actor_id' => $this->admin->id, 'subject_id' => $booking->id]);

        $retry = $this->payload($product, ['idempotency_key' => (string) Str::uuid()]);
        $this->actingAs($this->admin)->withSession($session)
            ->post("/admin/pencil-bookings/{$booking->id}/convert", $retry)
            ->assertRedirect("/admin/reservation-requests/{$request->id}");
        $this->assertDatabaseCount('reservation_requests', 1);
        $this->assertDatabaseCount('pencil_booking_events', 1);

        try {
            ReservationRequest::create([
                'reference' => 'ZEK-DUPLICATE-PENCIL',
                'idempotency_key' => (string) Str::uuid(),
                'origin' => 'pencil_booking',
                'pencil_booking_id' => $booking->id,
                'created_by' => $this->admin->id,
                'status' => 'submitted',
                'customer_name' => 'Duplicate Conversion',
                'customer_mobile' => '09170000000',
                'customer_email' => null,
                'event_date' => now('Asia/Manila')->addDays(20)->toDateString(),
                'event_start_time' => '10:00:00',
                'event_end_time' => '14:00:00',
                'timezone' => 'Asia/Manila',
                'venue_information' => 'Duplicate Venue',
                'guest_count' => 150,
                'main_package_version_id' => $product->currentVersion->id,
                'estimated_subtotal_minor' => 1,
                'currency' => 'PHP',
                'commercial_snapshot' => ['schema' => 1],
            ]);
            $this->fail('A pencil booking must link to only one reservation request.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_public_submission_remains_session_scoped_after_internal_submission_support_is_added(): void
    {
        $product = $this->publishedMain();
        $payload = $this->payload($product);
        unset($payload['selections']);
        $version = $product->currentVersion;
        $payload['idempotency_key'] = (string) Str::uuid();
        $selection = $this->selection($product);

        $this->withSession(['storefront.selection' => ['package:'.$product->id => $selection]])
            ->post('/reservation-request', $payload)
            ->assertRedirect();

        $request = ReservationRequest::sole();
        $this->assertSame('public', $request->origin);
        $this->assertNull($request->created_by);
        $this->assertNull($request->pencil_booking_id);
        $this->assertArrayNotHasKey('origin', $request->commercial_snapshot);
        $this->assertSame($version->id, $request->main_package_version_id);
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function payload(Product $product, array $overrides = []): array
    {
        return [...[
            'idempotency_key' => (string) Str::uuid(),
            'full_name' => 'Maria Santos',
            'mobile_number' => '09171234567',
            'email' => 'maria@example.test',
            'event_date' => now('Asia/Manila')->addDays(20)->toDateString(),
            'event_start_time' => '10:00',
            'venue_information' => 'General Santos City',
            'event_notes' => 'Internal request.',
            'selections' => [$this->selection($product)],
        ], ...$overrides];
    }

    /** @return array<string, mixed> */
    private function selection(Product $product): array
    {
        $version = $product->currentVersion;

        return [
            'type' => 'package',
            'product_id' => $product->id,
            'version_id' => $version->id,
            'quantity' => 150,
            'groups' => [[
                'quantity' => 150,
                'selections' => [
                    'food_items' => collect($version->snapshot()['menu']['food_items'])->pluck('id')->all(),
                    'food_sizes' => [],
                ],
            ]],
        ];
    }

    private function booking(): PencilBooking
    {
        return PencilBooking::create([
            'idempotency_key' => (string) Str::uuid(),
            'status' => 'active',
            'customer_name' => 'Pencil Customer',
            'customer_mobile' => '09179876543',
            'customer_email' => 'pencil@example.test',
            'event_date' => now('Asia/Manila')->addDays(20)->toDateString(),
            'event_start_time' => '10:00',
            'venue_information' => 'Pencil Venue',
            'guest_count' => 150,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);
    }

    private function publishedMain(): Product
    {
        $family = ProductFamily::where('code', 'main_catering')->sole();
        $category = DishCategory::create(['code' => 'pencil_main_'.Str::lower(Str::random(6)), 'name' => 'Pencil Main '.Str::lower(Str::random(6)), 'active' => true]);
        $first = Dish::create(['code' => 'pencil_food_'.Str::lower(Str::random(6)), 'category_id' => $category->id, 'name' => 'Pencil Rice', 'active' => true]);
        $second = Dish::create(['code' => 'pencil_food_'.Str::lower(Str::random(6)), 'category_id' => $category->id, 'name' => 'Pencil Chicken', 'active' => true]);
        $product = app(SaveProduct::class)->handle($this->admin, ['family_id' => $family->id, 'name' => 'Pencil Main Catering '.Str::random(5), 'description' => null, 'active' => true]);
        $draft = $product->versions()->sole();
        app(SavePackageVersion::class)->handle($this->admin, $product, ['base_price_pesos' => '750.00', 'selection_policy' => 'uniform', 'minimum_quantity' => 150, 'quantity_step' => 1, 'maximum_quantity' => null, 'total_selection_count' => 2, 'draft_revision' => $draft->draft_revision], $draft);
        $draft->refresh();
        app(SavePackageMenuConfiguration::class)->handle($this->admin, $draft, ['draft_revision' => $draft->draft_revision, 'total_selection_count' => 2, 'food_item_ids' => [$first->id, $second->id], 'required_category_ids' => []]);
        $draft->refresh();
        app(PublishPackageVersion::class)->handle($this->admin, $draft, $draft->draft_revision);

        return $product->fresh(['currentVersion']);
    }
}
