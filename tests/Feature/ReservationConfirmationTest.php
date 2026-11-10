<?php

namespace Tests\Feature;

use App\Actions\BusinessPolicies\SaveBusinessPolicy;
use App\Actions\Payments\RecordPayment;
use App\Actions\Payments\ReviewPayment;
use App\Actions\Quotation\CreateManualQuotation;
use App\Actions\Quotation\IssueQuotation;
use App\Actions\Quotation\RecordQuotationAcceptance;
use App\Actions\Reservations\CompleteReservation;
use App\Actions\Reservations\ConfirmReservation;
use App\Actions\Reservations\RecordReservationConfirmationOutcome;
use App\Models\BusinessPolicy;
use App\Models\PaymentMethod;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\User;
use App\Services\Reservations\ReservationCapacity;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReservationConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->administrator = User::factory()->create(['active' => true]);
        $this->administrator->roles()->attach(Role::where('code', 'admin')->sole());
        app(SaveBusinessPolicy::class)->handle($this->administrator, [
            'minimum_advance_booking_days' => 14,
            'daily_confirmed_event_capacity' => 3,
            'daily_guest_capacity' => null,
            'emergency_booking_requires_administrator' => true,
            'quotation_validity_days' => 7,
            'allow_quotation_validity_overrides' => true,
            'reservation_payment_type' => 'fixed_amount',
            'reservation_payment_amount' => '1000.00',
            'reservation_payment_basis_points' => null,
            'reservation_payment_deadline_hours' => 48,
            'final_payment_days_before_event' => 7,
            'customer_payment_upload_cutoff_days' => 7,
            'payment_account_name' => 'Test account',
            'payment_ewallet_number' => '09170000000',
            'payment_bank_account' => 'Test bank account',
            'payment_additional_instructions' => 'Test payment instructions.',
            'menu_finalization_days_after_acceptance' => 3,
        ]);
    }

    public function test_confirmation_review_route_renders_the_existing_page_component_and_exposes_readiness(): void
    {
        $quotation = $this->acceptedQuotation();

        $this->actingAs($this->administrator)
            ->withSession(['access_version' => $this->administrator->access_version])
            ->get(route('reservations.show', $quotation))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/reservations/show')
                ->where('quotation.id', $quotation->id)
                ->where('paymentReadiness.reservation_payment_satisfied', false)
                ->where('canConfirm', true));
    }

    public function test_confirmation_requires_verified_payment_then_records_one_capacity_commitment(): void
    {
        $quotation = $this->acceptedQuotation();
        try {
            app(ConfirmReservation::class)->handle($this->administrator, $quotation, 80);
            $this->fail('Confirmation must require the verified reservation-payment threshold.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment', $exception->errors());
        }

        $method = PaymentMethod::where('code', 'gcash')->firstOrFail();
        $method->update(['is_active' => true]);
        $payment = app(RecordPayment::class)->handle(null, $quotation, [
            'payment_method_id' => $method->id,
            'amount_minor' => 100000,
            'paid_at' => now(),
            'transaction_reference' => null,
            'notes' => null,
            'idempotency_key' => (string) str()->uuid(),
        ], 'customer_submission', UploadedFile::fake()->create('receipt.pdf', 10, 'application/pdf'));
        app(ReviewPayment::class)->verify($this->administrator, $payment, $quotation->currentVersion);

        $reservation = app(ConfirmReservation::class)->handle($this->administrator, $quotation->fresh(), 80);

        $this->assertSame('confirmed', $reservation->status->value);
        $this->assertSame(80, $reservation->guest_count);
        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'quotation_id' => $quotation->id]);
        $this->assertDatabaseHas('reservation_confirmation_events', [
            'quotation_id' => $quotation->id,
            'quotation_version_id' => $quotation->current_version_id,
            'reservation_id' => $reservation->id,
            'outcome' => 'confirmed',
        ]);

        try {
            app(ConfirmReservation::class)->handle($this->administrator, $quotation->fresh(), 80);
            $this->fail('A confirmed quotation version must not consume capacity twice.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reservation', $exception->errors());
        }
        $this->assertSame(1, Reservation::count());
    }

    public function test_staff_cannot_bypass_administrator_confirmation_through_the_http_route(): void
    {
        $staff = User::factory()->create(['active' => true]);
        $staff->roles()->attach(Role::where('code', 'staff')->sole());
        $quotation = $this->acceptedQuotation();

        $this->actingAs($staff)
            ->withSession(['access_version' => $staff->access_version])
            ->post(route('reservations.confirm', $quotation), ['guest_count' => 50])
            ->assertForbidden();
    }

    public function test_completed_reservation_continues_to_consume_daily_capacity(): void
    {
        $this->setDailyCapacity(1);
        $eventDate = now('Asia/Manila')->addDays(20)->toDateString();
        $first = $this->acceptedQuotation($eventDate);
        $this->verifyReservationPayment($first);
        $reservation = app(ConfirmReservation::class)->handle($this->administrator, $first, 80);

        $eventEnd = $reservation->event_date->setTimeFromTimeString($reservation->event_start_time)->addHours(4);
        CarbonImmutable::setTestNow($eventEnd);
        try {
            app(CompleteReservation::class)->handle($this->administrator, $reservation, 'Service completed.');
            $this->assertSame('completed', $reservation->fresh()->status->value);

            $second = $this->acceptedQuotation($eventDate);
            $this->verifyReservationPayment($second);
            try {
                app(ConfirmReservation::class)->handle($this->administrator, $second, 40);
                $this->fail('A completed reservation must continue to consume its event-date capacity.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('availability', $exception->errors());
            }
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_reservation_cannot_be_completed_before_its_scheduled_end(): void
    {
        $quotation = $this->acceptedQuotation();
        $this->verifyReservationPayment($quotation);
        $reservation = app(ConfirmReservation::class)->handle($this->administrator, $quotation, 50);

        try {
            app(CompleteReservation::class)->handle($this->administrator, $reservation, null);
            $this->fail('Completion before the scheduled end must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reservation', $exception->errors());
            $this->assertSame('confirmed', $reservation->fresh()->status->value);
        }
    }

    public function test_reservation_can_be_completed_after_its_scheduled_end(): void
    {
        $quotation = $this->acceptedQuotation();
        $this->verifyReservationPayment($quotation);
        $reservation = app(ConfirmReservation::class)->handle($this->administrator, $quotation, 50);
        $eventEnd = $reservation->event_date->setTimeFromTimeString($reservation->event_start_time)->addHours(4);
        CarbonImmutable::setTestNow($eventEnd);

        try {
            $completed = app(CompleteReservation::class)->handle($this->administrator, $reservation, 'Event concluded successfully.');
            $this->assertSame('completed', $completed->status->value);
            $this->assertNotNull($completed->completed_at);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_confirmation_outcome_uses_outstanding_balance_not_the_presence_of_a_verified_payment(): void
    {
        $quotation = $this->acceptedQuotation();
        $method = PaymentMethod::where('code', 'gcash')->firstOrFail();
        $method->update(['is_active' => true]);
        $payment = app(RecordPayment::class)->handle(null, $quotation, [
            'payment_method_id' => $method->id,
            'amount_minor' => 250100,
            'paid_at' => now(),
            'transaction_reference' => null,
            'notes' => null,
            'idempotency_key' => (string) str()->uuid(),
        ], 'customer_submission', UploadedFile::fake()->createWithContent('settled.pdf', 'settled-confirmation-proof'));
        app(ReviewPayment::class)->verify($this->administrator, $payment, $quotation->currentVersion);

        $event = app(RecordReservationConfirmationOutcome::class)->handle(
            $this->administrator,
            $quotation,
            'deferred',
            'Operations review is pending.',
        );

        $this->assertFalse($event->payment_snapshot['unresolved_financial_obligations']);
        $this->assertSame(0, $event->payment_snapshot['remaining_minor']);
    }

    public function test_daily_capacity_allows_the_configured_number_of_events_then_rejects_the_next_one(): void
    {
        $this->setDailyCapacity(2);
        $eventDate = now('Asia/Manila')->addDays(20)->toDateString();
        foreach ([60, 70] as $guests) {
            $quotation = $this->acceptedQuotation($eventDate);
            $this->verifyReservationPayment($quotation);
            app(ConfirmReservation::class)->handle($this->administrator, $quotation, $guests);
        }

        $third = $this->acceptedQuotation($eventDate);
        $this->verifyReservationPayment($third);
        try {
            app(ConfirmReservation::class)->handle($this->administrator, $third, 80);
            $this->fail('The daily capacity limit must prevent an additional confirmation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('availability', $exception->errors());
        }
        $this->assertSame(2, Reservation::count());
    }

    public function test_confirmation_waits_for_an_existing_event_date_capacity_lock(): void
    {
        $original = DB::getDefaultConnection();
        config()->set('database.connections.pgsql_capacity_owner', config('database.connections.pgsql'));
        config()->set('database.connections.pgsql_capacity_attempt', config('database.connections.pgsql'));
        $owner = DB::connection('pgsql_capacity_owner');
        $attempt = DB::connection('pgsql_capacity_attempt');
        try {
            $eventDate = now('Asia/Manila')->addDays(20)->toDateString();
            $policy = new BusinessPolicy([
                'daily_confirmed_event_capacity' => 1,
                'daily_guest_capacity' => null,
            ]);
            $owner->beginTransaction();
            $owner->selectOne('SELECT pg_advisory_xact_lock(hashtext(?))', ["reservation-capacity:{$eventDate}"]);
            $attempt->statement("SET lock_timeout TO '200ms'");
            DB::setDefaultConnection('pgsql_capacity_attempt');

            try {
                app(ReservationCapacity::class)->assessLocked(CarbonImmutable::parse($eventDate, 'Asia/Manila'), $policy, 50);
                $this->fail('A concurrent confirmation-capacity assessment must wait for the event-date lock.');
            } catch (QueryException $exception) {
                $this->assertStringContainsString('lock timeout', strtolower($exception->getMessage()));
            }
        } finally {
            DB::setDefaultConnection('pgsql_capacity_owner');
            if ($owner->transactionLevel() > 0) {
                $owner->rollBack();
            }
            $attempt->statement("SET lock_timeout TO '0'");
            DB::setDefaultConnection($original);
            DB::purge('pgsql_capacity_owner');
            DB::purge('pgsql_capacity_attempt');
        }
    }

    private function acceptedQuotation(?string $eventDate = null, string $eventStartTime = '10:00')
    {
        $quotation = app(CreateManualQuotation::class)->handle($this->administrator, [
            'customer_name' => 'Maria Santos',
            'customer_mobile' => '09171234567',
            'customer_email' => null,
            'event_date' => $eventDate ?? now('Asia/Manila')->addDays(20)->toDateString(),
            'event_start_time' => $eventStartTime,
            'venue_information' => 'City venue',
            'event_notes' => null,
            'lines' => [['description' => 'Catering', 'quantity' => 2, 'unit_price' => '1250.50']],
        ]);
        $version = app(IssueQuotation::class)->handle($this->administrator, $quotation->currentVersion)['version'];
        app(RecordQuotationAcceptance::class)->online($version, 'Maria Santos', 'test acceptance');

        return $quotation->fresh(['currentVersion']);
    }

    private function setDailyCapacity(int $capacity): void
    {
        BusinessPolicy::query()->where('key', 'current')->update(['daily_confirmed_event_capacity' => $capacity]);
    }

    private function verifyReservationPayment($quotation): void
    {
        $method = PaymentMethod::where('code', 'gcash')->firstOrFail();
        $method->update(['is_active' => true]);
        $payment = app(RecordPayment::class)->handle(null, $quotation, [
            'payment_method_id' => $method->id,
            'amount_minor' => 100000,
            'paid_at' => now(),
            'transaction_reference' => null,
            'notes' => null,
            'idempotency_key' => (string) str()->uuid(),
        ], 'customer_submission', UploadedFile::fake()->createWithContent('receipt-'.$quotation->id.'.pdf', 'reservation-payment-'.$quotation->id));
        app(ReviewPayment::class)->verify($this->administrator, $payment, $quotation->currentVersion);
    }
}
