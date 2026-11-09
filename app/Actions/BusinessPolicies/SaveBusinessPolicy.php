<?php

namespace App\Actions\BusinessPolicies;

use App\Models\BusinessPolicy;
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
                'payment_account_name' => trim($data['payment_account_name'] ?? $policy->payment_account_name),
                'payment_ewallet_number' => trim($data['payment_ewallet_number'] ?? $policy->payment_ewallet_number),
                'payment_bank_account' => trim($data['payment_bank_account'] ?? $policy->payment_bank_account),
                'payment_additional_instructions' => trim($data['payment_additional_instructions'] ?? $policy->payment_additional_instructions),
                'menu_finalization_days_after_acceptance' => $data['menu_finalization_days_after_acceptance'],
                'cancellation_refund_guidance' => trim($data['cancellation_refund_guidance'] ?? $policy->cancellation_refund_guidance),
                'updated_by' => $actor->id,
            ])->save();
            $this->audit->record('business_policy.updated', $actor, $policy, $before, $this->auditData($policy));

            return $policy->refresh();
        });
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
