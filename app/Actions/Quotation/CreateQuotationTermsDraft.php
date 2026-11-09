<?php

namespace App\Actions\Quotation;

use App\Enums\QuotationTermsStatus;
use App\Models\QuotationTermsVersion;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Catalog\MinorMoney;
use Illuminate\Support\Facades\DB;

class CreateQuotationTermsDraft
{
    public function __construct(protected MinorMoney $money, protected AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, array $data): QuotationTermsVersion
    {
        return DB::transaction(function () use ($actor, $data): QuotationTermsVersion {
            DB::select('select pg_advisory_xact_lock(?)', [41027]);
            $version = new QuotationTermsVersion;
            $version->forceFill(array_merge($this->attributes($data), [
                'version_number' => ((int) QuotationTermsVersion::query()->max('version_number')) + 1,
                'status' => QuotationTermsStatus::Draft,
                'created_by' => $actor->id,
            ]));
            $version->save();
            $this->audit->record('quotation_terms.draft_created', $actor, $version, null, $this->auditData($version));

            return $version;
        });
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    protected function attributes(array $data): array
    {
        $type = $data['reservation_payment_type'] ?? null;

        return [
            'tax_price_treatment_statement' => $this->nullableText($data['tax_price_treatment_statement'] ?? null),
            'reservation_payment_type' => $type,
            'reservation_payment_amount' => $type === 'fixed_amount' && filled($data['reservation_payment_amount'] ?? null)
                ? $this->money->fromPesos($data['reservation_payment_amount'], 'reservation_payment_amount') : null,
            'reservation_payment_basis_points' => $type === 'percentage' && array_key_exists('reservation_payment_basis_points', $data) && $data['reservation_payment_basis_points'] !== null
                ? (int) $data['reservation_payment_basis_points'] : null,
            'payment_deadline_hours' => (int) $data['payment_deadline_hours'],
            'menu_finalization_days' => (int) $data['menu_finalization_days'],
            'menu_finalization_terms' => $this->nullableText($data['menu_finalization_terms'] ?? null),
            'cancellation_refund_terms' => $this->nullableText($data['cancellation_refund_terms'] ?? null),
            'rescheduling_terms' => $this->nullableText($data['rescheduling_terms'] ?? null),
            'customer_conditions' => $this->nullableText($data['customer_conditions'] ?? null),
        ];
    }

    protected function nullableText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /** @return array<string, mixed> */
    protected function auditData(QuotationTermsVersion $version): array
    {
        return [
            'version_number' => $version->version_number,
            'status' => $version->status->value,
            'tax_price_treatment_statement' => $version->tax_price_treatment_statement,
            'reservation_payment_type' => $version->reservation_payment_type,
            'reservation_payment_amount' => $version->reservation_payment_amount,
            'reservation_payment_basis_points' => $version->reservation_payment_basis_points,
            'payment_deadline_hours' => $version->payment_deadline_hours,
            'menu_finalization_days' => $version->menu_finalization_days,
            'menu_finalization_terms' => $version->menu_finalization_terms,
            'cancellation_refund_terms' => $version->cancellation_refund_terms,
            'rescheduling_terms' => $version->rescheduling_terms,
            'customer_conditions' => $version->customer_conditions,
        ];
    }
}
