<?php

namespace Tests\Feature;

use App\Actions\BusinessPolicies\SaveBusinessPolicy;
use App\Actions\Payments\RecordPayment;
use App\Actions\Payments\ReviewPayment;
use App\Actions\Quotation\CreateManualQuotation;
use App\Actions\Quotation\IssueQuotation;
use App\Actions\Quotation\RecordQuotationAcceptance;
use App\Actions\Reservations\ConfirmReservation;
use App\Models\PaymentMethod;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    private function acceptedQuotation()
    {
        $quotation = app(CreateManualQuotation::class)->handle($this->administrator, [
            'customer_name' => 'Maria Santos',
            'customer_mobile' => '09171234567',
            'customer_email' => null,
            'event_date' => now('Asia/Manila')->addDays(20)->toDateString(),
            'event_start_time' => '10:00',
            'venue_information' => 'City venue',
            'event_notes' => null,
            'lines' => [['description' => 'Catering', 'quantity' => 2, 'unit_price' => '1250.50']],
        ]);
        $version = app(IssueQuotation::class)->handle($this->administrator, $quotation->currentVersion)['version'];
        app(RecordQuotationAcceptance::class)->online($version, 'Maria Santos', 'test acceptance');

        return $quotation->fresh(['currentVersion']);
    }
}
