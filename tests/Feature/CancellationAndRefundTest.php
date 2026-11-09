<?php

namespace Tests\Feature;

use App\Actions\Cancellations\CompleteRefund;
use App\Actions\Cancellations\CorrectCompletedRefund;
use App\Actions\Cancellations\CreateCancellationRequest;
use App\Actions\Cancellations\CreateRefund;
use App\Actions\Cancellations\ReviewCancellationRequest;
use App\Actions\Cancellations\ReviewRefund;
use App\Actions\Payments\RecordPayment;
use App\Actions\Payments\ReviewPayment;
use App\Actions\Quotation\CreateManualQuotation;
use App\Actions\Quotation\IssueQuotation;
use App\Actions\Quotation\RecordQuotationAcceptance;
use App\Actions\Reservations\ConfirmReservation;
use App\Actions\Reservations\CreateReservationChangeRequest;
use App\Enums\CancellationRequestStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\ReservationStatus;
use App\Models\CancellationRequest;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Quotation;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\User;
use App\Services\Cancellations\RefundBalance;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CancellationAndRefundTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->administrator = User::factory()->create(['active' => true]);
        $this->administrator->roles()->attach(Role::where('code', 'admin')->sole());
    }

    public function test_customer_cancellation_finalization_releases_only_confirmed_capacity_and_preserves_history(): void
    {
        [$quotation, $reservation] = $this->acceptedCase(true);
        $cancellation = app(CreateCancellationRequest::class)->handle(null, $quotation, [
            'idempotency_key' => (string) str()->uuid(), 'reason' => 'The event is no longer proceeding.',
        ], true);
        $this->assertSame('cancellation', $cancellation->kind);
        $this->assertSame(CancellationRequestStatus::PendingReview, $cancellation->status);
        $this->assertSame(1, $this->confirmedEventsOn($reservation->event_date->toDateString()));

        $finalized = app(ReviewCancellationRequest::class)->handle($this->administrator, $cancellation, true, 'Cancellation approved after review.');

        $this->assertSame(CancellationRequestStatus::RefundUnderReview, $finalized->status);
        $this->assertSame(ReservationStatus::Cancelled, $reservation->fresh()->status);
        $this->assertSame(0, $this->confirmedEventsOn($reservation->event_date->toDateString()));
        $this->assertDatabaseHas('reservation_status_events', ['reservation_id' => $reservation->id, 'to_status' => 'cancelled']);
        $this->assertDatabaseHas('audit_events', ['action' => 'cancellation.finalized', 'actor_id' => $this->administrator->id]);
    }

    public function test_unconfirmed_case_is_withdrawn_without_creating_a_reservation_cancellation(): void
    {
        [$quotation] = $this->acceptedCase(false);
        $cancellation = app(CreateCancellationRequest::class)->handle($this->administrator, $quotation, [
            'idempotency_key' => (string) str()->uuid(), 'reason' => 'Customer withdrew before confirmation.',
        ], false);
        $finalized = app(ReviewCancellationRequest::class)->handle($this->administrator, $cancellation, true, 'Withdrawal recorded.');

        $this->assertSame('withdrawal', $finalized->kind);
        $this->assertNull($finalized->reservation_id);
        $this->assertSame('withdrawn', $quotation->fresh()->status->value);
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_refunds_are_reviewed_completed_in_parts_and_cannot_exceed_the_linked_verified_payment(): void
    {
        [$quotation] = $this->acceptedCase(false);
        $payment = $this->verifiedPayment($quotation, 100000);
        $cancellation = $this->finalizeCancellation($quotation);
        $first = app(CreateRefund::class)->handle($this->administrator, $cancellation, [
            'payment_id' => $payment->id, 'amount_minor' => 40000, 'reason' => 'Partial approved refund.', 'idempotency_key' => (string) str()->uuid(),
        ]);
        $retry = app(CreateRefund::class)->handle($this->administrator, $cancellation, [
            'payment_id' => $payment->id, 'amount_minor' => 40000, 'reason' => 'Partial approved refund.', 'idempotency_key' => $first->idempotency_key,
        ]);
        $this->assertSame($first->id, $retry->id);
        try {
            app(CreateRefund::class)->handle($this->administrator, $cancellation, [
                'payment_id' => $payment->id, 'amount_minor' => 70000, 'reason' => 'Would over-promise the payment.', 'idempotency_key' => (string) str()->uuid(),
            ]);
            $this->fail('A pending refund must reserve its amount before another refund can be requested.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('refunds', 1);
        }
        app(ReviewRefund::class)->handle($this->administrator, $first, true, 'Approved.');
        app(CompleteRefund::class)->handle($this->administrator, $first, ['note' => 'Returned manually.']);
        $this->assertSame(60000, app(RefundBalance::class)->forPayment($payment->fresh())['held_minor']);

        $second = app(CreateRefund::class)->handle($this->administrator, $cancellation->fresh(), [
            'payment_id' => $payment->id, 'amount_minor' => 60000, 'reason' => 'Remaining approved refund.', 'idempotency_key' => (string) str()->uuid(),
        ]);
        app(ReviewRefund::class)->handle($this->administrator, $second, true, 'Approved.');
        app(CompleteRefund::class)->handle($this->administrator, $second, []);
        $this->assertSame(0, app(RefundBalance::class)->forPayment($payment->fresh())['held_minor']);
        $this->assertSame(CancellationRequestStatus::RefundCompleted, $cancellation->fresh()->status);

        $this->expectException(ValidationException::class);
        app(CreateRefund::class)->handle($this->administrator, $cancellation->fresh(), [
            'payment_id' => $payment->id, 'amount_minor' => 1, 'reason' => 'Attempted duplicate.', 'idempotency_key' => (string) str()->uuid(),
        ]);
    }

    public function test_refund_corrections_are_append_only_and_an_unrelated_payment_is_rejected(): void
    {
        [$quotation] = $this->acceptedCase(false);
        $payment = $this->verifiedPayment($quotation, 50000);
        $cancellation = $this->finalizeCancellation($quotation);
        $refund = app(CreateRefund::class)->handle($this->administrator, $cancellation, [
            'payment_id' => $payment->id, 'amount_minor' => 30000, 'reason' => 'Refund recorded incorrectly.', 'idempotency_key' => (string) str()->uuid(),
        ]);
        app(ReviewRefund::class)->handle($this->administrator, $refund, true, 'Approved.');
        app(CompleteRefund::class)->handle($this->administrator, $refund, []);
        app(CorrectCompletedRefund::class)->handle($this->administrator, $refund, -5000, 'Actual completed amount was lower.');
        $this->assertSame(25000, app(RefundBalance::class)->forPayment($payment->fresh())['refunded_minor']);
        $this->assertDatabaseHas('refund_corrections', ['refund_id' => $refund->id, 'amount_minor' => -5000]);

        [$otherQuotation] = $this->acceptedCase(false, 45);
        $otherPayment = $this->verifiedPayment($otherQuotation, 10000);
        $this->expectException(ValidationException::class);
        app(CreateRefund::class)->handle($this->administrator, $cancellation->fresh(), [
            'payment_id' => $otherPayment->id, 'amount_minor' => 1, 'reason' => 'Wrong customer payment.', 'idempotency_key' => (string) str()->uuid(),
        ]);
    }

    public function test_pending_rescheduling_blocks_cancellation_and_staff_cannot_review_it(): void
    {
        [$quotation, $reservation] = $this->acceptedCase(true);
        app(CreateReservationChangeRequest::class)->handle($this->administrator, $reservation, [
            'idempotency_key' => (string) str()->uuid(), 'venue_information' => 'A new venue',
        ], false);
        $this->expectException(ValidationException::class);
        app(CreateCancellationRequest::class)->handle($this->administrator, $quotation, [
            'idempotency_key' => (string) str()->uuid(), 'reason' => 'Conflicts with the active change.',
        ], false);
    }

    public function test_late_cancellation_is_recorded_for_review_without_an_automatic_financial_decision(): void
    {
        [$quotation] = $this->acceptedCase(false, 3);
        $cancellation = app(CreateCancellationRequest::class)->handle(null, $quotation, [
            'idempotency_key' => (string) str()->uuid(), 'reason' => 'The customer needs to cancel late.',
        ], true);

        $this->assertTrue($cancellation->policy_snapshot['inside_notice_period']);
        $this->assertSame(14, $cancellation->policy_snapshot['cancellation_notice_days']);
        $this->assertSame(CancellationRequestStatus::PendingReview, $cancellation->status);
        $this->assertDatabaseCount('refunds', 0);
    }

    public function test_only_an_administrator_can_finalize_a_customer_cancellation(): void
    {
        [$quotation] = $this->acceptedCase(false);
        $cancellation = app(CreateCancellationRequest::class)->handle(null, $quotation, [
            'idempotency_key' => (string) str()->uuid(), 'reason' => 'The customer requested withdrawal.',
        ], true);
        $staff = User::factory()->create(['active' => true]);
        $staff->roles()->attach(Role::where('code', 'staff')->sole());

        $this->expectException(AuthorizationException::class);
        app(ReviewCancellationRequest::class)->handle($staff, $cancellation, true, 'Staff cannot finalize this decision.');
    }

    public function test_administrator_can_record_a_reasoned_business_cancellation_without_a_second_reviewer(): void
    {
        [$quotation, $reservation] = $this->acceptedCase(true);
        $cancellation = app(CreateCancellationRequest::class)->handle($this->administrator, $quotation, [
            'idempotency_key' => (string) str()->uuid(), 'reason' => 'ZEK Catering cannot operationally service the event.',
        ], false, true);
        $finalized = app(ReviewCancellationRequest::class)->handle(
            $this->administrator,
            $cancellation,
            true,
            'Administrator finalized the business-initiated cancellation.',
        );

        $this->assertTrue($finalized->requested_by_business);
        $this->assertSame(ReservationStatus::Cancelled, $reservation->fresh()->status);
        $this->assertDatabaseHas('audit_events', ['action' => 'cancellation.finalized', 'actor_id' => $this->administrator->id]);
    }

    public function test_rejecting_a_refund_preserves_the_verified_payment_and_closes_financial_review(): void
    {
        [$quotation] = $this->acceptedCase(false);
        $payment = $this->verifiedPayment($quotation, 50000);
        $cancellation = $this->finalizeCancellation($quotation);
        $refund = app(CreateRefund::class)->handle($this->administrator, $cancellation, [
            'payment_id' => $payment->id, 'amount_minor' => 25000, 'reason' => 'Refund proposed for review.', 'idempotency_key' => (string) str()->uuid(),
        ]);
        $rejected = app(ReviewRefund::class)->handle($this->administrator, $refund, false, 'The proposed amount is not approved.');

        $this->assertSame(RefundStatus::Rejected, $rejected->status);
        $this->assertSame(PaymentStatus::Verified, $payment->fresh()->status);
        $this->assertSame(CancellationRequestStatus::Cancelled, $cancellation->fresh()->status);
        $this->assertSame(50000, app(RefundBalance::class)->forPayment($payment->fresh())['held_minor']);
    }

    /** @return array{0: Quotation, 1: Reservation|null} */
    private function acceptedCase(bool $confirm, int $daysUntilEvent = 35): array
    {
        $quotation = app(CreateManualQuotation::class)->handle($this->administrator, [
            'customer_name' => 'Maria Santos', 'customer_mobile' => '09171234567', 'customer_email' => null,
            'event_date' => now('Asia/Manila')->addDays($daysUntilEvent)->toDateString(), 'event_start_time' => '10:00',
            'venue_information' => 'City venue', 'event_notes' => null,
            'lines' => [['description' => 'Catering', 'quantity' => 80, 'unit_price' => '1250.50']],
        ]);
        $version = app(IssueQuotation::class)->handle($this->administrator, $quotation->currentVersion)['version'];
        app(RecordQuotationAcceptance::class)->online($version, 'Maria Santos', 'cancellation-test');
        if (! $confirm) {
            return [$quotation->fresh(['currentVersion']), null];
        }
        $payment = $this->verifiedPayment($quotation, 3000000);

        return [$quotation->fresh(), app(ConfirmReservation::class)->handle($this->administrator, $quotation->fresh(), 80)];
    }

    private function verifiedPayment(Quotation $quotation, int $amountMinor): Payment
    {
        $method = PaymentMethod::where('code', 'gcash')->firstOrFail();
        $method->update(['is_active' => true]);
        $payment = app(RecordPayment::class)->handle(null, $quotation, [
            'payment_method_id' => $method->id, 'amount_minor' => $amountMinor, 'paid_at' => now(),
            'transaction_reference' => null, 'notes' => null, 'idempotency_key' => (string) str()->uuid(),
        ], 'customer_submission', UploadedFile::fake()->createWithContent("refund-proof-{$quotation->id}.pdf", "refund payment proof {$quotation->id}"));
        app(ReviewPayment::class)->verify($this->administrator, $payment, $quotation->currentVersion);

        return $payment->fresh();
    }

    private function finalizeCancellation(Quotation $quotation): CancellationRequest
    {
        $cancellation = app(CreateCancellationRequest::class)->handle($this->administrator, $quotation, [
            'idempotency_key' => (string) str()->uuid(), 'reason' => 'Customer cancellation approved.',
        ], false);

        return app(ReviewCancellationRequest::class)->handle($this->administrator, $cancellation, true, 'Approved after financial review.');
    }

    private function confirmedEventsOn(string $date): int
    {
        return Reservation::query()->where('status', 'confirmed')->whereDate('event_date', $date)->count();
    }
}
