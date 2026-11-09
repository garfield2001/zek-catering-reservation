<?php

namespace App\Actions\Quotation;

use App\Enums\QuotationTermsStatus;
use App\Models\QuotationTermsVersion;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveQuotationTerms
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(User $actor, QuotationTermsVersion $version): QuotationTermsVersion
    {
        return DB::transaction(function () use ($actor, $version): QuotationTermsVersion {
            $version = QuotationTermsVersion::query()->lockForUpdate()->findOrFail($version->id);
            if ($version->status !== QuotationTermsStatus::Draft) {
                throw ValidationException::withMessages(['terms' => 'Only a draft quotation-terms version can be approved.']);
            }
            $this->assertComplete($version);
            DB::select('select pg_advisory_xact_lock(?)', [41027]);
            QuotationTermsVersion::query()
                ->where('status', QuotationTermsStatus::Approved)
                ->lockForUpdate()
                ->each(function (QuotationTermsVersion $current): void {
                    $current->forceFill(['status' => QuotationTermsStatus::Superseded])->save();
                });

            $version->forceFill([
                'status' => QuotationTermsStatus::Approved,
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ])->save();
            $this->audit->record('quotation_terms.approved', $actor, $version, ['status' => 'draft'], [
                'version_number' => $version->version_number,
                'approved_at' => $version->approved_at?->toIso8601String(),
                'superseded_prior_terms' => true,
            ]);

            return $version->refresh();
        });
    }

    private function assertComplete(QuotationTermsVersion $version): void
    {
        $errors = [];
        foreach (['tax_price_treatment_statement', 'menu_finalization_terms', 'cancellation_refund_terms', 'rescheduling_terms', 'customer_conditions'] as $field) {
            if (blank($version->{$field})) {
                $errors[$field] = 'Complete this business-owner-approved term before approval.';
            }
        }
        if (! in_array($version->reservation_payment_type, ['fixed_amount', 'percentage'], true)) {
            $errors['reservation_payment_type'] = 'Choose whether the reservation payment is a fixed amount or percentage.';
        } elseif ($version->reservation_payment_type === 'fixed_amount' && $version->reservation_payment_amount === null) {
            $errors['reservation_payment_amount'] = 'Enter the approved fixed reservation payment.';
        } elseif ($version->reservation_payment_type === 'percentage' && $version->reservation_payment_basis_points === null) {
            $errors['reservation_payment_basis_points'] = 'Enter the approved reservation-payment percentage.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
