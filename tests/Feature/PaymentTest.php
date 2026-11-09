<?php

namespace Tests\Feature;

use App\Actions\BusinessPolicies\SaveBusinessPolicy;
use App\Actions\Payments\ReallocatePayment;
use App\Actions\Payments\RecordPayment;
use App\Actions\Payments\ReviewPayment;
use App\Actions\Quotation\CreateManualQuotation;
use App\Actions\Quotation\CreateQuotationRevision;
use App\Actions\Quotation\IssueQuotation;
use App\Actions\Quotation\RecordQuotationAcceptance;
use App\Models\PaymentMethod;
use App\Models\Permission;
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

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::where('code', 'admin')->sole());
        app(SaveBusinessPolicy::class)->handle($this->admin, ['minimum_advance_booking_days' => 14, 'emergency_booking_requires_administrator' => true, 'quotation_validity_days' => 7, 'allow_quotation_validity_overrides' => true, 'reservation_payment_type' => 'fixed_amount', 'reservation_payment_amount' => '1000.00', 'reservation_payment_basis_points' => null, 'reservation_payment_deadline_hours' => 48, 'final_payment_days_before_event' => 7, 'menu_finalization_days_after_acceptance' => 3]);
    }

    public function test_administrator_verifies_partial_and_full_payments_with_correct_balance(): void
    {
        $quote = $this->acceptedQuote();
        $method = PaymentMethod::query()->where('code', 'gcash')->firstOrFail();
        $method->update(['is_active' => true]);
        $first = app(RecordPayment::class)->handle($this->admin, $quote, $this->data($method, '400.00'), 'administrator_exception', null, false);
        app(ReviewPayment::class)->verify($this->admin, $first, $quote->currentVersion);
        $balance = app(PaymentBalance::class)->forQuotation($quote->fresh());
        $this->assertSame(40000, $balance['verified_minor']);
        $this->assertSame(60000, $balance['reservation_remaining_minor']);
    }

    public function test_administrator_can_record_a_cash_payment_and_payment_method_changes_are_audited(): void
    {
        $quote = $this->acceptedQuote();
        $method = PaymentMethod::query()->where('code', 'cash')->firstOrFail();
        $method->update(['is_active' => true]);
        $this->actingAs($this->admin)->withSession(['access_version' => $this->admin->access_version])
            ->post(route('payments.store'), ['quotation_id' => $quote->id, 'payment_method_id' => $method->id, 'amount' => '250.25', 'paid_at' => now()->format('Y-m-d H:i:s'), 'transaction_reference' => null, 'notes' => 'Cash at office.', 'exception_reason' => null, 'idempotency_key' => (string) str()->uuid()])
            ->assertRedirect();
        $payment = $quote->payments()->sole();
        $this->assertSame('staff_recorded', $payment->source);
        $this->assertSame('verified', $payment->status->value);
        $this->assertSame(25025, app(PaymentBalance::class)->forQuotation($quote->fresh())['verified_minor']);

        $this->actingAs($this->admin)->withSession(['access_version' => $this->admin->access_version])
            ->put(route('payments.methods.update', $method), ['name' => 'Office cash', 'instructions' => null, 'destination_information' => null, 'is_offline' => true, 'is_active' => false, 'display_order' => 0])
            ->assertRedirect();
        $this->assertDatabaseHas('payment_methods', ['id' => $method->id, 'name' => 'Office cash', 'is_active' => false]);
        $this->assertDatabaseHas('audit_events', ['action' => 'payment_method.updated', 'actor_id' => $this->admin->id, 'subject_id' => $method->id]);
    }

    public function test_pending_payment_is_verified_once_and_overpayment_is_visible(): void
    {
        $quote = $this->acceptedQuote();
        $method = PaymentMethod::query()->where('code', 'gcash')->firstOrFail();
        $method->update(['is_active' => true]);
        $payment = app(RecordPayment::class)->handle(null, $quote, $this->data($method, '3000.00'), 'customer_submission', UploadedFile::fake()->create('receipt.pdf', 20, 'application/pdf'));
        app(ReviewPayment::class)->verify($this->admin, $payment, $quote->currentVersion);
        $balance = app(PaymentBalance::class)->forQuotation($quote->fresh());
        $this->assertSame(250100, $balance['total_minor']);
        $this->assertSame(300000, $balance['verified_minor']);
        $this->assertSame(49900, $balance['overpayment_minor']);
        $this->assertSame(0, $balance['remaining_minor']);
        $this->expectException(ValidationException::class);
        app(ReviewPayment::class)->verify($this->admin, $payment->fresh(), $quote->currentVersion);
    }

    public function test_staff_cannot_verify_and_rejection_preserves_resubmission_history(): void
    {
        $quote = $this->acceptedQuote();
        $method = PaymentMethod::query()->where('code', 'gcash')->firstOrFail();
        $method->update(['is_active' => true]);
        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('code', 'staff')->sole());
        $staff->permissionOverrides()->attach(Permission::where('code', 'payment.record')->sole(), ['effect' => 'allow']);
        $payment = app(RecordPayment::class)->handle($staff, $quote, $this->data($method, '100.00'), 'staff_recorded');
        $this->actingAs($staff)->withSession(['access_version' => $staff->access_version])->post(route('payments.review', $payment), ['action' => 'verify'])->assertForbidden();
        $this->actingAs($staff)->withSession(['access_version' => $staff->access_version])->put(route('payments.methods.update', $method), ['name' => 'Manipulated method', 'instructions' => null, 'destination_information' => null, 'is_offline' => false, 'is_active' => false, 'display_order' => 0])->assertForbidden();
        app(ReviewPayment::class)->reject($this->admin, $payment, 'Receipt is unreadable.');
        $this->assertSame('rejected', $payment->fresh()->status->value);
    }

    public function test_reallocation_preserves_history_and_prevents_double_counting(): void
    {
        $quote = $this->acceptedQuote();
        $method = PaymentMethod::query()->where('code', 'gcash')->firstOrFail();
        $method->update(['is_active' => true]);
        $payment = app(RecordPayment::class)->handle(null, $quote, $this->data($method, '500.00'), 'customer_submission', UploadedFile::fake()->create('receipt.pdf', 10, 'application/pdf'));
        app(ReviewPayment::class)->verify($this->admin, $payment, $quote->currentVersion);
        $revision = app(CreateQuotationRevision::class)->handle($this->admin, $quote->fresh());
        $issued = app(IssueQuotation::class)->handle($this->admin, $revision)['version'];
        app(RecordQuotationAcceptance::class)->online($issued, 'Maria Santos', 'revised');
        app(ReallocatePayment::class)->handle($this->admin, $payment, $issued, 'Customer accepted revised quotation.');
        $payment = $payment->fresh();
        $active = $payment->allocation;
        $original = $payment->allocationHistory()->oldest('id')->firstOrFail();

        $this->assertTrue($issued->acceptance()->exists());
        $this->assertSame($issued->id, $active?->quotation_version_id);
        $this->assertNotNull($original->superseded_at);
        $this->assertSame(2, $payment->allocationHistory()->count());
        $this->assertSame($issued->id, app(PaymentBalance::class)->forQuotation($quote->fresh())['quotation_version_id']);
        $this->assertSame(50000, app(PaymentBalance::class)->forQuotation($quote->fresh())['verified_minor']);

        try {
            app(ReallocatePayment::class)->handle($this->admin, $payment, $issued, 'Repeated attempt.');
            $this->fail('A repeated allocation must be rejected.');
        } catch (ValidationException) {
            $this->assertSame(2, $payment->fresh()->allocationHistory()->count());
        }
    }

    public function test_reversal_after_reallocation_removes_only_the_active_balance(): void
    {
        $quote = $this->acceptedQuote();
        $method = PaymentMethod::query()->where('code', 'gcash')->firstOrFail();
        $method->update(['is_active' => true]);
        $payment = app(RecordPayment::class)->handle(null, $quote, $this->data($method, '400.00'), 'customer_submission', UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'));
        app(ReviewPayment::class)->verify($this->admin, $payment, $quote->currentVersion);
        $revision = app(CreateQuotationRevision::class)->handle($this->admin, $quote->fresh());
        $issued = app(IssueQuotation::class)->handle($this->admin, $revision)['version'];
        app(RecordQuotationAcceptance::class)->online($issued, 'Maria Santos', 'revised');
        app(ReallocatePayment::class)->handle($this->admin, $payment, $issued, 'Customer accepted revised quotation.');
        app(ReviewPayment::class)->reverse($this->admin, $payment->fresh(), 'Bank confirmation showed no settled payment.');
        $balance = app(PaymentBalance::class)->forQuotation($quote->fresh());
        $this->assertSame(0, $balance['verified_minor']);
        $this->assertSame(250100, $balance['remaining_minor']);
        $this->assertSame($issued->id, $balance['quotation_version_id']);
        $this->assertSame(2, $payment->fresh()->allocationHistory()->count());
    }

    public function test_partial_payments_remain_current_when_allocated_across_accepted_revisions(): void
    {
        $quote = $this->acceptedQuote();
        $method = PaymentMethod::query()->where('code', 'gcash')->firstOrFail();
        $method->update(['is_active' => true]);
        $first = app(RecordPayment::class)->handle(null, $quote, $this->data($method, '400.00'), 'customer_submission', UploadedFile::fake()->create('first.pdf', 10, 'application/pdf'));
        app(ReviewPayment::class)->verify($this->admin, $first, $quote->currentVersion);

        $revision = app(CreateQuotationRevision::class)->handle($this->admin, $quote->fresh());
        $issued = app(IssueQuotation::class)->handle($this->admin, $revision)['version'];
        app(RecordQuotationAcceptance::class)->online($issued, 'Maria Santos', 'revised');
        app(ReallocatePayment::class)->handle($this->admin, $first, $issued, 'Customer accepted revised quotation.');
        $second = app(RecordPayment::class)->handle(null, $quote, $this->data($method, '250.00'), 'customer_submission', UploadedFile::fake()->createWithContent('second.pdf', 'A distinct second payment proof.'));
        app(ReviewPayment::class)->verify($this->admin, $second, $issued);

        $balance = app(PaymentBalance::class)->forQuotation($quote->fresh());
        $this->assertSame($issued->id, $balance['quotation_version_id']);
        $this->assertSame(65000, $balance['verified_minor']);
        $this->assertSame(35000, $balance['reservation_remaining_minor']);
        $this->assertSame(2, $issued->paymentAllocations()->whereNull('superseded_at')->count());
    }

    public function test_verified_early_exception_can_be_explicitly_linked_once_to_an_accepted_quotation(): void
    {
        $method = PaymentMethod::query()->where('code', 'cash')->firstOrFail();
        $method->update(['is_active' => true]);
        $payment = app(RecordPayment::class)->handle($this->admin, null, [...$this->data($method, '600.00'), 'exception_reason' => 'Cash was received before the quotation was accepted.'], 'administrator_exception');
        app(ReviewPayment::class)->verify($this->admin, $payment);
        $this->assertNull($payment->fresh()->allocation);

        $quote = $this->acceptedQuote();
        app(ReallocatePayment::class)->handle($this->admin, $payment->fresh(), $quote->currentVersion, 'Customer accepted the related quotation.');

        $payment = $payment->fresh();
        $this->assertSame($quote->id, $payment->quotation_id);
        $this->assertSame($quote->currentVersion->id, $payment->allocation?->quotation_version_id);
        $this->assertSame(60000, app(PaymentBalance::class)->forQuotation($quote->fresh())['verified_minor']);
    }

    public function test_duplicate_submissions_are_idempotent_and_reused_proof_is_rejected(): void
    {
        $quote = $this->acceptedQuote();
        $method = PaymentMethod::query()->where('code', 'gcash')->firstOrFail();
        $method->update(['is_active' => true]);
        $data = $this->data($method, '100.00');
        $first = app(RecordPayment::class)->handle(null, $quote, $data, 'customer_submission', UploadedFile::fake()->createWithContent('first.pdf', 'same-proof-content'));
        $repeated = app(RecordPayment::class)->handle(null, $quote, $data, 'customer_submission', UploadedFile::fake()->createWithContent('retry.pdf', 'a browser retry must not create a second payment'));
        $this->assertSame($first->id, $repeated->id);
        $this->assertSame(1, $quote->payments()->count());

        try {
            app(RecordPayment::class)->handle(null, $quote, $this->data($method, '100.00'), 'customer_submission', UploadedFile::fake()->createWithContent('duplicate.pdf', 'same-proof-content'));
            $this->fail('A reused proof must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('proof', $exception->errors());
        }
    }

    public function test_postgresql_allocation_history_cannot_be_rewritten_or_cross_quoted(): void
    {
        $quote = $this->acceptedQuote();
        $other = $this->acceptedQuote();
        $method = PaymentMethod::query()->where('code', 'gcash')->firstOrFail();
        $method->update(['is_active' => true]);
        $payment = app(RecordPayment::class)->handle(null, $quote, $this->data($method, '100.00'), 'customer_submission', UploadedFile::fake()->createWithContent('proof.pdf', 'integrity-proof'));
        app(ReviewPayment::class)->verify($this->admin, $payment, $quote->currentVersion);
        $allocation = $payment->fresh()->allocation;

        try {
            DB::transaction(fn () => DB::table('payment_allocations')->where('id', $allocation?->id)->update(['quotation_version_id' => $other->currentVersion->id]));
            $this->fail('Allocation ownership must be enforced by PostgreSQL.');
        } catch (QueryException) {
            $this->assertSame($quote->currentVersion->id, $payment->fresh()->allocation?->quotation_version_id);
        }
    }

    public function test_verified_payment_cannot_be_allocated_to_an_unrelated_quotation(): void
    {
        $quote = $this->acceptedQuote();
        $other = $this->acceptedQuote();
        $method = PaymentMethod::query()->where('code', 'gcash')->firstOrFail();
        $method->update(['is_active' => true]);
        $payment = app(RecordPayment::class)->handle(null, $quote, $this->data($method, '100.00'), 'customer_submission', UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'));
        app(ReviewPayment::class)->verify($this->admin, $payment, $quote->currentVersion);
        try {
            app(ReallocatePayment::class)->handle($this->admin, $payment->fresh(), $other->currentVersion, 'Attempted unrelated allocation.');
            $this->fail('An unrelated quotation must never receive a payment allocation.');
        } catch (ValidationException) {
            $this->assertSame($quote->currentVersion->id, $payment->fresh()->allocation?->quotation_version_id);
            $this->assertSame(1, $payment->fresh()->allocationHistory()->count());
        }
    }

    private function acceptedQuote(): Quotation
    {
        $quote = app(CreateManualQuotation::class)->handle($this->admin, ['customer_name' => 'Maria Santos', 'customer_mobile' => '09171234567', 'customer_email' => null, 'event_date' => now('Asia/Manila')->addDays(20)->toDateString(), 'event_start_time' => '10:00', 'venue_information' => 'City', 'event_notes' => null, 'lines' => [['description' => 'Catering', 'quantity' => 2, 'unit_price' => '1250.50']]]);
        $issued = app(IssueQuotation::class)->handle($this->admin, $quote->currentVersion)['version'];
        app(RecordQuotationAcceptance::class)->online($issued, 'Maria Santos', 'test');

        return $quote->fresh(['currentVersion']);
    }

    private function data(PaymentMethod $method, string $amount): array
    {
        return ['payment_method_id' => $method->id, 'amount_minor' => (int) round(((float) $amount) * 100), 'paid_at' => now(), 'transaction_reference' => null, 'notes' => null, 'idempotency_key' => (string) str()->uuid()];
    }
}
