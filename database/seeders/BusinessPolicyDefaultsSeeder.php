<?php

namespace Database\Seeders;

use App\Models\BusinessPolicy;
use Illuminate\Database\Seeder;

/**
 * Establishes the owner-approved first-run reservation-payment policy.
 * It only upgrades the exact untouched migration placeholder and never
 * replaces an Administrator's later configuration.
 */
class BusinessPolicyDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        $policy = BusinessPolicy::query()->where('key', 'current')->first();
        if ($policy === null) {
            throw new \RuntimeException('The current Business Policy must be created by migrations before seeding.');
        }

        $isUntouchedPlaceholder = $policy->updated_by === null
            && $policy->reservation_payment_type === 'fixed_amount'
            && (int) $policy->reservation_payment_amount === 0
            && $policy->reservation_payment_basis_points === null;
        if (! $isUntouchedPlaceholder) {
            return;
        }

        $policy->forceFill([
            'reservation_payment_type' => 'percentage',
            'reservation_payment_amount' => null,
            'reservation_payment_basis_points' => 2000,
        ])->save();
    }
}
