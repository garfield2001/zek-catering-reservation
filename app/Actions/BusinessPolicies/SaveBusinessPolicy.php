<?php

namespace App\Actions\BusinessPolicies;

use App\Models\BusinessPolicy;
use App\Models\PaymentInstructionVersion;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Catalog\MinorMoney;
use Illuminate\Support\Facades\DB;

class SaveBusinessPolicy
{
    public function __construct(private MinorMoney $money, private AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, array $data): BusinessPolicy
    {
        return DB::transaction(function () use ($actor, $data): BusinessPolicy {
            $policy = BusinessPolicy::query()->where('key', 'current')->lockForUpdate()->sole();
            $before = $this->auditData($policy);
            $instructions = [
                'account_name' => trim($data['payment_account_name'] ?? $policy->payment_account_name),
                'ewallet_number' => trim($data['payment_ewallet_number'] ?? $policy->payment_ewallet_number),
                'bank_account' => trim($data['payment_bank_account'] ?? $policy->payment_bank_account),
                'additional_instructions' => trim($data['payment_additional_instructions'] ?? $policy->payment_additional_instructions),
            ];
            $type = $data['reservation_payment_type'];
            $policy->forceFill([
                'minimum_advance_booking_days' => $data['minimum_advance_booking_days'],
                'minimum_rescheduling_notice_days' => $data['minimum_rescheduling_notice_days'] ?? $policy->minimum_rescheduling_notice_days,
                'cancellation_notice_days' => $data['cancellation_notice_days'] ?? $policy->cancellation_notice_days,
                'daily_confirmed_event_capacity' => $data['daily_confirmed_event_capacity'] ?? $policy->daily_confirmed_event_capacity,
                'daily_guest_capacity' => array_key_exists('daily_guest_capacity', $data) ? $data['daily_guest_capacity'] : $policy->daily_guest_capacity,
                'emergency_booking_requires_administrator' => $data['emergency_booking_requires_administrator'],
                'quotation_validity_days' => $data['quotation_validity_days'],
                'allow_quotation_validity_overrides' => $data['allow_quotation_validity_overrides'],
                'reservation_payment_type' => $type,
                'reservation_payment_amount' => $type === 'fixed_amount'
                    ? $this->money->fromPesos($data['reservation_payment_amount'], 'reservation_payment_amount')
                    : null,
                'reservation_payment_basis_points' => $type === 'percentage'
                    ? $data['reservation_payment_basis_points']
                    : null,
                'reservation_payment_deadline_hours' => $data['reservation_payment_deadline_hours'],
                'final_payment_days_before_event' => $data['final_payment_days_before_event'],
                'customer_payment_upload_cutoff_days' => $data['customer_payment_upload_cutoff_days'] ?? $policy->customer_payment_upload_cutoff_days,
                'payment_account_name' => $instructions['account_name'],
                'payment_ewallet_number' => $instructions['ewallet_number'],
                'payment_bank_account' => $instructions['bank_account'],
                'payment_additional_instructions' => $instructions['additional_instructions'],
                'menu_finalization_days_after_acceptance' => $data['menu_finalization_days_after_acceptance'],
                'cancellation_refund_guidance' => trim($data['cancellation_refund_guidance'] ?? $policy->cancellation_refund_guidance),
                'updated_by' => $actor->id,
            ])->save();
            $current = $policy->currentPaymentInstructionVersion()->lockForUpdate()->first();
            if ($current === null || $this->instructionsChanged($current, $instructions)) {
                $version = PaymentInstructionVersion::create([
                    'business_policy_id' => $policy->id,
                    'version_number' => ((int) PaymentInstructionVersion::query()->where('business_policy_id', $policy->id)->max('version_number')) + 1,
                    ...$instructions,
                    'effective_at' => now(),
                    'created_by' => $actor->id,
                ]);
                $policy->forceFill(['current_payment_instruction_version_id' => $version->id])->save();
                $this->audit->record('payment_instruction_version.created', $actor, $version, null, $version->snapshot());
            }
            $this->audit->record('business_policy.updated', $actor, $policy, $before, $this->auditData($policy));

            return $policy->refresh();
        });
    }

    /** @param array<string, string> $instructions */
    private function instructionsChanged(PaymentInstructionVersion $version, array $instructions): bool
    {
        return $version->account_name !== $instructions['account_name']
            || $version->ewallet_number !== $instructions['ewallet_number']
            || $version->bank_account !== $instructions['bank_account']
            || $version->additional_instructions !== $instructions['additional_instructions'];
    }

    /** @return array<string, mixed> */
    private function auditData(BusinessPolicy $policy): array
    {
        return $policy->only([
            'minimum_advance_booking_days',
            'minimum_rescheduling_notice_days',
            'cancellation_notice_days',
            'event_duration_hours',
            'daily_confirmed_event_capacity',
            'daily_guest_capacity',
            'emergency_booking_requires_administrator',
            'quotation_validity_days',
            'allow_quotation_validity_overrides',
            'reservation_payment_type',
            'reservation_payment_amount',
            'reservation_payment_basis_points',
            'reservation_payment_deadline_hours',
            'final_payment_days_before_event',
            'customer_payment_upload_cutoff_days',
            'payment_account_name',
            'payment_ewallet_number',
            'payment_bank_account',
            'payment_additional_instructions',
            'menu_finalization_days_after_acceptance',
            'cancellation_refund_guidance',
        ]);
    }
}
