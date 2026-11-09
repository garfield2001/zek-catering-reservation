<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            DROP INDEX IF EXISTS quotation_terms_one_approved_production;
            ALTER TABLE quotation_terms_versions DROP CONSTRAINT quotation_terms_version_integrity;
            ALTER TABLE quotation_terms_versions DROP COLUMN test_configuration;
            ALTER TABLE quotation_terms_versions ADD CONSTRAINT quotation_terms_version_integrity CHECK (
                status IN ('draft', 'approved', 'superseded')
                AND payment_deadline_hours > 0 AND menu_finalization_days > 0
                AND (status <> 'approved' OR (
                    reservation_payment_type IN ('fixed_amount', 'percentage')
                    AND btrim(coalesce(tax_price_treatment_statement, '')) <> ''
                    AND btrim(coalesce(menu_finalization_terms, '')) <> ''
                    AND btrim(coalesce(cancellation_refund_terms, '')) <> ''
                    AND btrim(coalesce(rescheduling_terms, '')) <> ''
                    AND btrim(coalesce(customer_conditions, '')) <> ''
                    AND ((reservation_payment_type = 'fixed_amount' AND reservation_payment_amount IS NOT NULL AND reservation_payment_amount >= 0 AND reservation_payment_basis_points IS NULL)
                        OR (reservation_payment_type = 'percentage' AND reservation_payment_amount IS NULL AND reservation_payment_basis_points IS NOT NULL AND reservation_payment_basis_points BETWEEN 0 AND 10000))
                    AND approved_by IS NOT NULL AND approved_at IS NOT NULL
                ))
            );
            CREATE UNIQUE INDEX quotation_terms_one_active_approved
                ON quotation_terms_versions ((status)) WHERE status = 'approved';
            SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Restoring retired quotation test-configuration semantics is intentionally manual.');
    }
};
