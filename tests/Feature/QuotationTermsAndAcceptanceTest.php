<?php

namespace Tests\Feature;

use App\Actions\BusinessPolicies\SaveBusinessPolicy;
use App\Actions\Quotation\ApplyDirectQuotationAdjustment;
use App\Actions\Quotation\CreateManualQuotation;
use App\Actions\Quotation\IssueQuotation;
use App\Actions\Quotation\RecordQuotationAcceptance;
use App\Actions\Quotation\RequestQuotationAdjustment;
use App\Actions\Quotation\ReviewQuotationAdjustment;
use App\Models\BusinessPolicy;
use App\Models\Permission;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class QuotationTermsAndAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::where('code', 'admin')->sole());
    }

    public function test_configured_business_policies_allow_issuance_and_are_frozen_on_the_issued_version(): void
    {
        $quote = $this->manualQuote();
        $issued = app(IssueQuotation::class)->handle($this->admin, $quote->currentVersion)['version'];

        $this->assertSame('issued', $issued->status->value);
        $this->assertNull($issued->terms_version_id);
        $this->assertSame(2, $issued->terms_snapshot['schema']);
        $this->assertSame(7, $issued->terms_snapshot['quotation_validity_days']);
        $this->assertDatabaseHas('audit_events', ['action' => 'quotation.issued', 'actor_id' => $this->admin->id, 'subject_id' => $issued->id]);
    }

    public function test_policy_changes_apply_only_to_future_issued_versions(): void
    {
        $first = app(IssueQuotation::class)->handle($this->admin, $this->manualQuote()->currentVersion)['version'];
        app(SaveBusinessPolicy::class)->handle($this->admin, $this->policyData([
            'quotation_validity_days' => 12,
            'reservation_payment_type' => 'percentage',
            'reservation_payment_amount' => null,
            'reservation_payment_basis_points' => 2500,
        ]));
        $second = app(IssueQuotation::class)->handle($this->admin, $this->manualQuote()->currentVersion)['version'];

        $this->assertSame(7, $first->fresh()->terms_snapshot['quotation_validity_days']);
        $this->assertSame(12, $second->terms_snapshot['quotation_validity_days']);
        $this->assertSame(2500, $second->terms_snapshot['reservation_payment']['basis_points']);
        $this->assertDatabaseHas('audit_events', ['action' => 'business_policy.updated', 'actor_id' => $this->admin->id]);
    }

    public function test_payment_instruction_changes_create_immutable_history_and_do_not_rewrite_issued_terms(): void
    {
        $first = app(IssueQuotation::class)->handle($this->admin, $this->manualQuote()->currentVersion)['version'];
        $initialVersion = $first->terms_snapshot['payment_instruction_version']['id'];
        app(SaveBusinessPolicy::class)->handle($this->admin, $this->policyData(['payment_account_name' => 'ZEK Catering Updated Account']));
        $policy = BusinessPolicy::where('key', 'current')->sole();
        $this->assertNotSame($initialVersion, $policy->current_payment_instruction_version_id);
        $this->assertSame($initialVersion, $first->fresh()->terms_snapshot['payment_instruction_version']['id']);
        $this->assertDatabaseCount('payment_instruction_versions', 2);
        $this->assertDatabaseHas('audit_events', ['action' => 'payment_instruction_version.created', 'actor_id' => $this->admin->id]);
    }

    public function test_only_administrators_manage_policies_while_authorized_staff_can_view_them(): void
    {
        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('code', 'staff')->sole());
        $staff->permissionOverrides()->attach(Permission::where('code', 'quotation.view')->sole(), ['effect' => 'allow']);
        $session = ['access_version' => $staff->access_version];

        $this->actingAs($staff)->withSession($session)->get('/admin/business-policies')->assertOk();
        $this->actingAs($staff)->withSession($session)->put('/admin/business-policies', $this->policyData())->assertForbidden();
        $this->actingAs($this->admin)->withSession(['access_version' => $this->admin->access_version])
            ->put('/admin/business-policies', $this->policyData(['minimum_advance_booking_days' => 21]))->assertRedirect();
        $this->assertSame(21, BusinessPolicy::where('key', 'current')->sole()->minimum_advance_booking_days);
    }

    public function test_policy_can_disallow_an_explicit_validity_override_without_blocking_standard_issuance(): void
    {
        app(SaveBusinessPolicy::class)->handle($this->admin, $this->policyData(['allow_quotation_validity_overrides' => false]));
        $quote = $this->manualQuote();
        app(IssueQuotation::class)->handle($this->admin, $quote->currentVersion);
        $this->assertSame('issued', $quote->fresh()->currentVersion->status->value);

        $this->expectException(ValidationException::class);
        app(IssueQuotation::class)->handle($this->admin, $this->manualQuote()->currentVersion, CarbonImmutable::now('Asia/Manila')->addDays(2));
    }

    public function test_acceptance_uses_the_issued_policy_snapshot_for_deadlines(): void
    {
        app(SaveBusinessPolicy::class)->handle($this->admin, $this->policyData([
            'reservation_payment_type' => 'percentage',
            'reservation_payment_amount' => null,
            'reservation_payment_basis_points' => 2500,
        ]));
        $quote = $this->manualQuote();
        $issued = app(IssueQuotation::class)->handle($this->admin, $quote->currentVersion)['version'];
        $acceptance = app(RecordQuotationAcceptance::class)->online($issued, 'Maria Santos', 'customer-access:1');

        $this->assertSame($acceptance->accepted_at->addHours(48)->toIso8601String(), $acceptance->payment_due_at->toIso8601String());
        $this->assertSame($acceptance->accepted_at->addDays(3)->toIso8601String(), $acceptance->menu_finalization_due_at->toIso8601String());
        $this->assertSame($quote->event_date->toDateString(), $acceptance->final_payment_due_at->addDays(7)->toDateString());
        $this->assertSame('accepted', $quote->fresh()->status->value);
    }

    public function test_administrator_can_directly_apply_an_audited_adjustment_to_a_draft(): void
    {
        $quote = $this->manualQuote();
        $adjustment = app(ApplyDirectQuotationAdjustment::class)->handle($this->admin, $quote->currentVersion, '125.50', 'discount', 'Goodwill discount for this inquiry');

        $this->assertSame('approved', $adjustment->status);
        $this->assertSame($this->admin->id, $adjustment->reviewed_by);
        $this->assertSame('-12550', $quote->currentVersion->fresh()->adjustment_minor);
        $this->assertDatabaseHas('audit_events', ['action' => 'quotation.adjustment_applied_directly', 'actor_id' => $this->admin->id]);
    }

    public function test_staff_requests_an_adjustment_and_cannot_bypass_administrator_authorization(): void
    {
        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('code', 'staff')->sole());
        $staff->permissionOverrides()->attach(Permission::where('code', 'quotation.adjustment.request')->sole(), ['effect' => 'allow']);
        $quote = $this->manualQuote();
        $request = app(RequestQuotationAdjustment::class)->handle($staff, $quote->currentVersion, '50', 'surcharge', 'Additional logistics requirement');
        app(ReviewQuotationAdjustment::class)->handle($this->admin, $request, true, 'Approved by Administrator');

        $this->assertSame('approved', $request->fresh()->status);
        $this->actingAs($staff)->withSession(['access_version' => $staff->access_version])
            ->post(route('quotation-versions.adjustments.direct', $quote->currentVersion), ['adjustment_amount' => '10', 'direction' => 'discount', 'reason' => 'Attempted bypass'])
            ->assertForbidden();
    }

    public function test_direct_adjustment_and_manual_quotation_requests_keep_field_error_keys(): void
    {
        $quote = $this->manualQuote();
        $session = ['access_version' => $this->admin->access_version];
        $this->actingAs($this->admin)->withSession($session)
            ->post(route('quotation-versions.adjustments.direct', $quote->currentVersion), ['adjustment_amount' => 'invalid', 'direction' => 'discount', 'reason' => ''])
            ->assertSessionHasErrors(['adjustment_amount', 'reason']);
        $this->actingAs($this->admin)->withSession($session)->post('/admin/quotations', [
            'customer_name' => ['not a string'], 'customer_mobile' => '', 'customer_email' => 'not-an-email',
            'event_date' => 'not-a-date', 'event_start_time' => 'not-a-time',
            'lines' => [['description' => ['not a string'], 'quantity' => 0, 'unit_price' => 'not-a-price']],
        ])->assertSessionHasErrors(['customer_name', 'customer_mobile', 'customer_email', 'event_date', 'event_start_time', 'lines.0.description', 'lines.0.quantity', 'lines.0.unit_price']);
    }

    public function test_legacy_terms_route_redirects_to_business_policies_and_cannot_create_new_terms(): void
    {
        $session = ['access_version' => $this->admin->access_version];
        $this->actingAs($this->admin)->withSession($session)->get('/admin/quotation-terms')->assertRedirect('/admin/business-policies');
        $this->actingAs($this->admin)->withSession($session)->post('/admin/quotation-terms')->assertGone();
    }

    private function manualQuote(): Quotation
    {
        return app(CreateManualQuotation::class)->handle($this->admin, [
            'customer_name' => 'Maria Santos', 'customer_mobile' => '09171234567', 'customer_email' => 'maria@example.test',
            'event_date' => now('Asia/Manila')->addDays(20)->toDateString(), 'event_start_time' => '10:00', 'venue_information' => 'General Santos City', 'event_notes' => null,
            'lines' => [['description' => 'Custom catering service', 'quantity' => 2, 'unit_price' => '1250.50']],
        ]);
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function policyData(array $overrides = []): array
    {
        return array_merge([
            'minimum_advance_booking_days' => 14,
            'minimum_rescheduling_notice_days' => 14,
            'cancellation_notice_days' => 14,
            'daily_confirmed_event_capacity' => 3,
            'daily_guest_capacity' => null,
            'emergency_booking_requires_administrator' => true,
            'quotation_validity_days' => 7,
            'allow_quotation_validity_overrides' => true,
            'reservation_payment_type' => 'fixed_amount',
            'reservation_payment_amount' => '0.00',
            'reservation_payment_basis_points' => null,
            'reservation_payment_deadline_hours' => 48,
            'final_payment_days_before_event' => 7,
            'customer_payment_upload_cutoff_days' => 7,
            'payment_account_name' => 'SAMPLE — ZEK Catering',
            'payment_ewallet_number' => 'REPLACE WITH ACTUAL NUMBER',
            'payment_bank_account' => 'REPLACE WITH ACTUAL ACCOUNT',
            'payment_additional_instructions' => 'Replace these sample details before collecting real payments.',
            'menu_finalization_days_after_acceptance' => 3,
            'cancellation_refund_guidance' => 'Cancellation and refund requests are reviewed individually.',
        ], $overrides);
    }
}
