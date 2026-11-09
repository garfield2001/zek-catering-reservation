<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\PencilBooking;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PencilBookingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->administrator = User::factory()->create();
        $this->administrator->roles()->attach(Role::where('code', 'admin')->sole());
    }

    public function test_authorized_user_can_create_update_and_close_a_non_binding_pencil_booking_without_storing_contact_data_in_history(): void
    {
        $session = ['access_version' => $this->administrator->access_version];
        $payload = $this->payload();

        $this->actingAs($this->administrator)->withSession($session)
            ->post('/admin/pencil-bookings', $payload)
            ->assertRedirect('/admin/pencil-bookings');

        $booking = PencilBooking::sole();
        $this->assertSame('active', $booking->status);
        $this->assertNull($booking->converted_reservation_request_id);
        $this->assertDatabaseHas('pencil_booking_events', [
            'pencil_booking_id' => $booking->id,
            'event_type' => 'created',
            'actor_id' => $this->administrator->id,
        ]);

        $changedName = 'Updated prospect name';
        $this->actingAs($this->administrator)->withSession($session)
            ->put("/admin/pencil-bookings/{$booking->id}", [
                ...$payload,
                'customer_name' => $changedName,
                'notes' => 'Updated follow-up note.',
            ])
            ->assertRedirect();

        $event = $booking->events()->where('event_type', 'updated')->sole();
        $audit = AuditEvent::query()
            ->where('action', 'pencil_booking.updated')
            ->where('subject_type', 'pencil_booking')
            ->where('subject_id', $booking->id)
            ->sole();
        $this->assertSame(['customer_name', 'notes'], $event->details['changed_fields']);
        $this->assertSame($event->details, $audit->before_data);
        $this->assertSame($event->details, $audit->after_data);
        $this->assertStringNotContainsString($changedName, json_encode($event->details, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString($payload['customer_mobile'], json_encode($audit->before_data, JSON_THROW_ON_ERROR));

        $this->actingAs($this->administrator)->withSession($session)
            ->post("/admin/pencil-bookings/{$booking->id}/close", ['status' => 'did_not_proceed', 'reason' => 'Customer chose another date.'])
            ->assertRedirect();

        $this->assertSame('did_not_proceed', $booking->fresh()->status);
        $this->assertDatabaseHas('pencil_booking_events', ['pencil_booking_id' => $booking->id, 'event_type' => 'did_not_proceed']);
    }

    public function test_pencil_booking_events_are_append_only_and_manage_permission_is_required(): void
    {
        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('code', 'staff')->sole());
        $payload = $this->payload();

        $this->actingAs($staff)->withSession(['access_version' => $staff->access_version])
            ->post('/admin/pencil-bookings', $payload)
            ->assertForbidden();

        $staff->permissionOverrides()->attach(Permission::where('code', 'pencil_booking.manage')->sole(), ['effect' => 'allow']);
        $staff->refresh();
        $this->actingAs($staff)->withSession(['access_version' => $staff->access_version])
            ->post('/admin/pencil-bookings', $payload)
            ->assertRedirect();

        $event = PencilBooking::sole()->events()->sole();
        try {
            $event->update(['event_type' => 'forged']);
            $this->fail('Pencil booking lifecycle events must be append-only.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'idempotency_key' => (string) Str::uuid(),
            'customer_name' => 'Original prospect name',
            'customer_mobile' => '09171234567',
            'customer_email' => null,
            'event_date' => now('Asia/Manila')->addDays(20)->toDateString(),
            'event_start_time' => '10:00',
            'venue_information' => 'Prospect venue',
            'guest_count' => 150,
            'notes' => 'Initial private note.',
            'follow_up_on' => now('Asia/Manila')->addDays(2)->toDateString(),
        ];
    }
}
