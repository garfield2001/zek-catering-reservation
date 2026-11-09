<?php

namespace Tests\Feature;

use App\Actions\BusinessPolicies\SaveBusinessPolicy;
use App\Actions\Payments\ReallocatePayment;
use App\Actions\Payments\RecordPayment;
use App\Actions\Payments\ReviewPayment;
use App\Actions\Quotation\CreateManualQuotation;
use App\Actions\Quotation\IssueQuotation;
use App\Actions\Quotation\RecordQuotationAcceptance;
use App\Actions\Reservations\AgreeReservationChangeQuotation;
use App\Actions\Reservations\ApplyReservationChangeRequest;
use App\Actions\Reservations\ConfirmReservation;
use App\Actions\Reservations\CreateReservationChangeRequest;
use App\Actions\Reservations\ReviewReservationChangeRequest;
use App\Enums\ReservationChangeRequestStatus;
use App\Models\BusinessPolicy;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Quotation;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReservationChangeRequestTest extends TestCase
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
            'minimum_rescheduling_notice_days' => 14,
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

    public function test_administrator_applies_an_approved_date_change_without_releasing_capacity_early(): void
    {
        [$quotation, $reservation] = $this->confirmedReservation();
        $newDate = now('Asia/Manila')->addDays(40)->toDateString();
        $change = app(CreateReservationChangeRequest::class)->handle($this->administrator, $reservation, [
            'idempotency_key' => (string) str()->uuid(),
            'event_date' => $newDate,
        ], false);

        $this->assertSame(1, Reservation::whereDate('event_date', $reservation->event_date)->count());
        $reviewed = app(ReviewReservationChangeRequest::class)->handle($this->administrator, $change, true, 'Date works for the customer.', null);
        $this->assertSame(ReservationChangeRequestStatus::AwaitingReconfirmation, $reviewed->status);

        $applied = app(ApplyReservationChangeRequest::class)->handle($this->administrator, $reviewed);

        $this->assertSame(ReservationChangeRequestStatus::Applied, $applied->status);
        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'event_date' => $newDate]);
        $this->assertDatabaseHas('reservation_change_request_events', ['reservation_change_request_id' => $change->id, 'to_status' => 'applied']);
        $this->assertSame(0, Reservation::whereDate('event_date', $reservation->event_date)->count());
        $this->assertSame(1, Reservation::whereDate('event_date', $newDate)->count());
        $this->assertSame($quotation->id, $applied->quotation_id);
    }

    public function test_short_notice_change_requires_an_administrator_exception_reason(): void
    {
        [, $reservation] = $this->confirmedReservation();
        $change = app(CreateReservationChangeRequest::class)->handle($this->administrator, $reservation, [
            'idempotency_key' => (string) str()->uuid(),
            'event_date' => now('Asia/Manila')->addDays(2)->toDateString(),
        ], false);

        try {
            app(ReviewReservationChangeRequest::class)->handle($this->administrator, $change, true, '', null);
            $this->fail('A short-notice change must require an exception reason.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('exception_reason', $exception->errors());
        }

        $reviewed = app(ReviewReservationChangeRequest::class)->handle($this->administrator, $change, true, 'Customer emergency request.', 'Approved after the operational review.');
        $this->assertSame(ReservationChangeRequestStatus::AwaitingReconfirmation, $reviewed->status);
        $this->assertSame('Approved after the operational review.', $reviewed->exception_reason);
    }

    public function test_commercial_guest_change_requires_agreement_and_explicit_payment_reallocation_before_apply(): void
    {
        [$quotation, $reservation, $payment] = $this->confirmedReservation();
        $change = app(CreateReservationChangeRequest::class)->handle($this->administrator, $reservation, [
            'idempotency_key' => (string) str()->uuid(),
            'guest_count' => 90,
        ], false);

        $reviewed = app(ReviewReservationChangeRequest::class)->handle($this->administrator, $change, true, 'Reprice for the larger event.', null);
        $revision = $reviewed->quotationVersion;
        $this->assertNotNull($revision);
        $this->assertSame(ReservationChangeRequestStatus::AwaitingCustomerAgreement, $reviewed->status);
        $this->assertSame('draft', $revision->status->value);

        $issued = app(IssueQuotation::class)->handle($this->administrator, $revision)['version'];
        $agreed = app(AgreeReservationChangeQuotation::class)->handle($reviewed, 'Maria Santos');
        $this->assertSame(ReservationChangeRequestStatus::AwaitingReconfirmation, $agreed->status);

        try {
            app(ApplyReservationChangeRequest::class)->handle($this->administrator, $agreed);
            $this->fail('Verified payment must not automatically move to a revised quotation version.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment', $exception->errors());
        }

        app(ReallocatePayment::class)->handle($this->administrator, $payment, $issued, 'Customer agreed to the revised quotation.');
        $applied = app(ApplyReservationChangeRequest::class)->handle($this->administrator, $agreed);

        $this->assertSame(ReservationChangeRequestStatus::Applied, $applied->status);
        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'confirmed_quotation_version_id' => $issued->id,
            'guest_count' => 90,
        ]);
        $this->assertDatabaseHas('payment_allocations', [
            'payment_id' => $payment->id,
            'quotation_version_id' => $issued->id,
            'superseded_at' => null,
        ]);
    }

    public function test_only_authorized_staff_can_prepare_a_change_and_staff_cannot_review_it(): void
    {
        [, $reservation] = $this->confirmedReservation();
        $staff = User::factory()->create(['active' => true]);
        $staff->roles()->attach(Role::where('code', 'staff')->sole());

        $this->actingAs($staff)
            ->withSession(['access_version' => $staff->access_version])
            ->post(route('reservations.changes.store', $reservation), [
                'idempotency_key' => (string) str()->uuid(),
                'venue_information' => 'New venue',
            ])
            ->assertForbidden();
    }

    public function test_one_active_change_is_enforced_while_idempotent_retry_returns_the_original_request(): void
    {
        [, $reservation] = $this->confirmedReservation();
        $key = (string) str()->uuid();
        $first = app(CreateReservationChangeRequest::class)->handle($this->administrator, $reservation, [
            'idempotency_key' => $key,
            'venue_information' => 'First proposed venue',
        ], false);

        $retry = app(CreateReservationChangeRequest::class)->handle($this->administrator, $reservation, [
            'idempotency_key' => $key,
            'venue_information' => 'First proposed venue',
        ], false);
        $this->assertSame($first->id, $retry->id);

        $this->expectException(ValidationException::class);
        try {
            app(CreateReservationChangeRequest::class)->handle($this->administrator, $reservation, [
                'idempotency_key' => (string) str()->uuid(),
                'venue_information' => 'Second proposed venue',
            ], false);
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('changes', $exception->errors());

            throw $exception;
        }
    }

    public function test_declining_a_change_preserves_the_confirmed_reservation(): void
    {
        [, $reservation] = $this->confirmedReservation();
        $change = app(CreateReservationChangeRequest::class)->handle($this->administrator, $reservation, [
            'idempotency_key' => (string) str()->uuid(),
            'venue_information' => 'Venue not available',
        ], false);

        $declined = app(ReviewReservationChangeRequest::class)->handle(
            $this->administrator,
            $change,
            false,
            'The proposed venue cannot support the event.',
            null,
        );

        $this->assertSame(ReservationChangeRequestStatus::Declined, $declined->status);
        $this->assertSame('City venue', $reservation->fresh()->venue_information);
        $this->assertDatabaseHas('reservation_change_request_events', [
            'reservation_change_request_id' => $change->id,
            'to_status' => 'declined',
        ]);
    }

    public function test_capacity_refusal_leaves_the_existing_reservation_and_change_pending_reconfirmation(): void
    {
        [, $fullReservation] = $this->confirmedReservation(30);
        [, $movingReservation] = $this->confirmedReservation(40);
        BusinessPolicy::query()->where('key', 'current')->update(['daily_confirmed_event_capacity' => 1]);
        $originalDate = $movingReservation->event_date->toDateString();
        $change = app(CreateReservationChangeRequest::class)->handle($this->administrator, $movingReservation, [
            'idempotency_key' => (string) str()->uuid(),
            'event_date' => $fullReservation->event_date->toDateString(),
        ], false);
        $reviewed = app(ReviewReservationChangeRequest::class)->handle($this->administrator, $change, true, 'Review complete.', null);

        try {
            app(ApplyReservationChangeRequest::class)->handle($this->administrator, $reviewed);
            $this->fail('Capacity must be rechecked before a reservation change is applied.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('availability', $exception->errors());
        }

        $this->assertSame($originalDate, $movingReservation->fresh()->event_date->toDateString());
        $this->assertSame(ReservationChangeRequestStatus::AwaitingReconfirmation, $change->fresh()->status);
    }

    /** @return array{0: Quotation, 1: Reservation, 2: Payment} */
    private function confirmedReservation(int $daysUntilEvent = 30): array
    {
        $quotation = app(CreateManualQuotation::class)->handle($this->administrator, [
            'customer_name' => 'Maria Santos',
            'customer_mobile' => '09171234567',
            'customer_email' => null,
            'event_date' => now('Asia/Manila')->addDays($daysUntilEvent)->toDateString(),
            'event_start_time' => '10:00',
            'venue_information' => 'City venue',
            'event_notes' => null,
            'lines' => [['description' => 'Catering', 'quantity' => 80, 'unit_price' => '1250.50']],
        ]);
        $version = app(IssueQuotation::class)->handle($this->administrator, $quotation->currentVersion)['version'];
        app(RecordQuotationAcceptance::class)->online($version, 'Maria Santos', 'test acceptance');
        $method = PaymentMethod::where('code', 'gcash')->firstOrFail();
        $method->update(['is_active' => true]);
        $payment = app(RecordPayment::class)->handle(null, $quotation, [
            'payment_method_id' => $method->id,
            'amount_minor' => 100000,
            'paid_at' => now(),
            'transaction_reference' => null,
            'notes' => null,
            'idempotency_key' => (string) str()->uuid(),
        ], 'customer_submission', UploadedFile::fake()->createWithContent("receipt-{$daysUntilEvent}.pdf", "payment-proof-{$daysUntilEvent}"));
        app(ReviewPayment::class)->verify($this->administrator, $payment, $quotation->currentVersion);

        return [$quotation, app(ConfirmReservation::class)->handle($this->administrator, $quotation->fresh(), 80), $payment];
    }
}
