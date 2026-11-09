<?php

namespace Tests\Feature;

use App\Actions\BusinessPolicies\SaveBusinessPolicy;
use App\Actions\Payments\ReplaceCustomerPaymentEvidence;
use App\Actions\Payments\ReviewPayment;
use App\Actions\Payments\SubmitCustomerPaymentEvidence;
use App\Actions\Quotation\CreateManualQuotation;
use App\Actions\Quotation\CreateQuotationRevision;
use App\Actions\Quotation\IssueQuotation;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\User;
use App\Services\Payments\PaymentBalance;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerPaymentEvidenceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::where('code', 'admin')->sole());
        app(SaveBusinessPolicy::class)->handle($this->admin, $this->policyData());
    }

    public function test_first_customer_receipt_transactionally_accepts_the_displayed_quotation_and_stays_unverified(): void
    {
        $quote = $this->issuedQuote();
        $payment = app(SubmitCustomerPaymentEvidence::class)->handle($quote, ['idempotency_key' => (string) str()->uuid()], UploadedFile::fake()->image('receipt.png'));

        $this->assertSame(PaymentStatus::PendingVerification, $payment->status);
        $this->assertNull($payment->amount_minor);
        $this->assertSame($quote->currentVersion->id, $payment->submitted_quotation_version_id);
        $this->assertTrue($quote->fresh()->currentVersion->acceptance()->exists());
        $this->assertSame(1, $payment->evidenceVersions()->count());
        $this->assertSame(0, app(PaymentBalance::class)->forQuotation($quote->fresh())['verified_minor']);
        $this->assertDatabaseHas('audit_events', ['action' => 'quotation.accepted_online']);
    }

    public function test_administrator_verifies_a_legitimate_partial_payment_without_rejecting_it(): void
    {
        $quote = $this->issuedQuote();
        $payment = app(SubmitCustomerPaymentEvidence::class)->handle($quote, ['idempotency_key' => (string) str()->uuid()], UploadedFile::fake()->image('receipt.png'));
        app(ReviewPayment::class)->verify($this->admin, $payment, null, 500000, 'We verified your first payment.');

        $balance = app(PaymentBalance::class)->forQuotation($quote->fresh());
        $this->assertSame(500000, $balance['verified_minor']);
        $this->assertSame(460000, $balance['reservation_remaining_minor']);
        $this->assertFalse($balance['reservation_payment_satisfied']);
        $this->assertSame('We verified your first payment.', $payment->fresh()->customer_message);
    }

    public function test_first_receipt_accepts_the_current_issued_revision_not_an_older_issued_version(): void
    {
        $quote = $this->issuedQuote();
        $revision = app(CreateQuotationRevision::class)->handle($this->admin, $quote);
        app(IssueQuotation::class)->handle($this->admin, $revision);
        $quote = $quote->fresh(['currentVersion']);

        $payment = app(SubmitCustomerPaymentEvidence::class)->handle($quote, ['idempotency_key' => (string) str()->uuid()], UploadedFile::fake()->image('revision-receipt.png'));

        $this->assertSame($quote->currentVersion->id, $payment->submitted_quotation_version_id);
        $this->assertTrue($quote->currentVersion->acceptance()->exists());
    }

    public function test_rejected_customer_evidence_can_be_replaced_without_creating_another_payment(): void
    {
        $quote = $this->issuedQuote();
        $payment = app(SubmitCustomerPaymentEvidence::class)->handle($quote, ['idempotency_key' => (string) str()->uuid()], UploadedFile::fake()->image('receipt.png'));
        app(ReviewPayment::class)->reject($this->admin, $payment, 'Receipt is unreadable.');
        app(ReplaceCustomerPaymentEvidence::class)->handle($payment->fresh(), UploadedFile::fake()->createWithContent('replacement.png', 'different receipt evidence'));

        $this->assertSame(1, Payment::query()->where('quotation_id', $quote->id)->count());
        $this->assertSame(PaymentStatus::PendingVerification, $payment->fresh()->status);
        $this->assertSame(2, $payment->evidenceVersions()->count());
    }

    public function test_customer_upload_is_blocked_after_the_manila_preparation_cutoff_without_creating_a_payment(): void
    {
        $quote = $this->issuedQuote(now('Asia/Manila')->addDays(6)->toDateString());
        try {
            app(SubmitCustomerPaymentEvidence::class)->handle($quote, ['idempotency_key' => (string) str()->uuid()], UploadedFile::fake()->image('late.png'));
            $this->fail('The hard preparation cutoff must block a customer upload.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment', $exception->errors());
            $this->assertSame(0, Payment::query()->count());
            $this->assertSame('issued', $quote->fresh()->currentVersion->status->value);
        }
    }

    public function test_an_expired_unaccepted_quotation_is_marked_expired_and_cannot_be_accepted_by_receipt_upload(): void
    {
        $this->travelTo(now('Asia/Manila')->startOfDay());
        $quote = $this->issuedQuote(now('Asia/Manila')->addDays(30)->toDateString());
        $this->travel(8)->days();
        try {
            app(SubmitCustomerPaymentEvidence::class)->handle($quote, ['idempotency_key' => (string) str()->uuid()], UploadedFile::fake()->image('expired.png'));
            $this->fail('An expired quotation cannot be accepted by receipt upload.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment', $exception->errors());
            $this->assertSame('expired', $quote->fresh()->currentVersion->status->value);
            $this->assertSame(0, Payment::query()->count());
        } finally {
            $this->travelBack();
        }
    }

    public function test_existing_pending_receipt_can_still_be_verified_after_the_customer_upload_cutoff(): void
    {
        $this->travelTo(now('Asia/Manila')->startOfDay());
        $quote = $this->issuedQuote(now('Asia/Manila')->addDays(8)->toDateString());
        $payment = app(SubmitCustomerPaymentEvidence::class)->handle($quote, ['idempotency_key' => (string) str()->uuid()], UploadedFile::fake()->image('receipt.png'));

        $this->travel(2)->days();
        app(ReviewPayment::class)->verify($this->admin, $payment->fresh(), null, 960000);

        $this->assertSame(PaymentStatus::Verified, $payment->fresh()->status);
        $this->travelBack();
    }

    public function test_postgresql_protects_submission_ownership_and_append_only_receipt_history(): void
    {
        $quote = $this->issuedQuote();
        $other = $this->issuedQuote();
        $payment = app(SubmitCustomerPaymentEvidence::class)->handle($quote, ['idempotency_key' => (string) str()->uuid()], UploadedFile::fake()->image('receipt.png'));
        $evidence = $payment->evidenceVersions()->sole();

        try {
            DB::transaction(fn () => DB::table('payments')->where('id', $payment->id)->update(['submitted_quotation_version_id' => $other->currentVersion->id]));
            $this->fail('A customer receipt must remain attached to a version of its own quotation.');
        } catch (QueryException) {
            $this->assertSame($quote->currentVersion->id, $payment->fresh()->submitted_quotation_version_id);
        }

        try {
            DB::transaction(fn () => DB::table('payment_evidence_versions')->where('id', $evidence->id)->update(['path' => 'tampered-proof']));
            $this->fail('Receipt history must be append-only.');
        } catch (QueryException) {
            $this->assertDatabaseHas('payment_evidence_versions', ['id' => $evidence->id, 'version_number' => 1]);
        }
    }

    public function test_administrator_can_open_historic_receipts_only_through_their_own_payment(): void
    {
        $quote = $this->issuedQuote();
        $payment = app(SubmitCustomerPaymentEvidence::class)->handle($quote, ['idempotency_key' => (string) str()->uuid()], UploadedFile::fake()->image('receipt.png'));
        app(ReviewPayment::class)->reject($this->admin, $payment, 'Please provide a clearer image.');
        app(ReplaceCustomerPaymentEvidence::class)->handle($payment->fresh(), UploadedFile::fake()->image('replacement.png', 20, 20));
        $historic = $payment->evidenceVersions()->oldest('version_number')->firstOrFail();
        $otherPayment = app(SubmitCustomerPaymentEvidence::class)->handle($this->issuedQuote(), ['idempotency_key' => (string) str()->uuid()], UploadedFile::fake()->image('other-receipt.png', 30, 30));

        $this->actingAs($this->admin)->withSession(['access_version' => $this->admin->access_version])
            ->get(route('payments.evidence-versions.preview', [$payment, $historic]))
            ->assertOk();
        $this->actingAs($this->admin)->withSession(['access_version' => $this->admin->access_version])
            ->get(route('payments.evidence-versions.preview', [$otherPayment, $historic]))
            ->assertNotFound();
    }

    private function issuedQuote(?string $eventDate = null): Quotation
    {
        $quote = app(CreateManualQuotation::class)->handle($this->admin, [
            'customer_name' => 'Maria Santos', 'customer_mobile' => '09171234567', 'customer_email' => null,
            'event_date' => $eventDate ?? now('Asia/Manila')->addDays(20)->toDateString(), 'event_start_time' => '10:00',
            'venue_information' => 'City', 'event_notes' => null,
            'lines' => [['description' => 'Catering', 'quantity' => 1, 'unit_price' => '48000.00']],
        ]);
        app(IssueQuotation::class)->handle($this->admin, $quote->currentVersion);

        return $quote->fresh(['currentVersion']);
    }

    /** @return array<string, mixed> */
    private function policyData(): array
    {
        return [
            'minimum_advance_booking_days' => 14, 'daily_confirmed_event_capacity' => 3, 'daily_guest_capacity' => null,
            'emergency_booking_requires_administrator' => true, 'quotation_validity_days' => 7,
            'allow_quotation_validity_overrides' => true, 'reservation_payment_type' => 'fixed_amount',
            'reservation_payment_amount' => '9600.00', 'reservation_payment_basis_points' => null,
            'reservation_payment_deadline_hours' => 48, 'final_payment_days_before_event' => 7,
            'customer_payment_upload_cutoff_days' => 7, 'payment_account_name' => 'SAMPLE — ZEK Catering',
            'payment_ewallet_number' => 'REPLACE WITH ACTUAL NUMBER', 'payment_bank_account' => 'REPLACE WITH ACTUAL ACCOUNT',
            'payment_additional_instructions' => 'Replace these sample details before collecting real payments.',
            'menu_finalization_days_after_acceptance' => 3,
        ];
    }
}
