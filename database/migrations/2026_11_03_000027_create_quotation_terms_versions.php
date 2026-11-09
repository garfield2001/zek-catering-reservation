<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_terms_versions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('version_number')->unique();
            $table->string('status', 16)->default('draft');
            $table->boolean('test_configuration')->default(false);
            $table->text('tax_price_treatment_statement')->nullable();
            $table->string('reservation_payment_type', 16)->nullable();
            $table->bigInteger('reservation_payment_amount')->nullable();
            $table->unsignedInteger('reservation_payment_basis_points')->nullable();
            $table->unsignedSmallInteger('payment_deadline_hours')->default(48);
            $table->unsignedSmallInteger('menu_finalization_days')->default(3);
            $table->text('menu_finalization_terms')->nullable();
            $table->text('cancellation_refund_terms')->nullable();
            $table->text('rescheduling_terms')->nullable();
            $table->text('customer_conditions')->nullable();
            $table->string('business_owner_name')->nullable();
            $table->text('approval_evidence')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampsTz();
        });

        DB::unprepared(<<<'SQL'
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
                    AND btrim(coalesce(business_owner_name, '')) <> ''
                    AND btrim(coalesce(approval_evidence, '')) <> ''
                    AND ((reservation_payment_type = 'fixed_amount' AND reservation_payment_amount IS NOT NULL AND reservation_payment_amount >= 0 AND reservation_payment_basis_points IS NULL)
                        OR (reservation_payment_type = 'percentage' AND reservation_payment_amount IS NULL AND reservation_payment_basis_points IS NOT NULL AND reservation_payment_basis_points BETWEEN 0 AND 10000))
                    AND approved_by IS NOT NULL AND approved_at IS NOT NULL
                ))
            );
            CREATE UNIQUE INDEX quotation_terms_one_approved_production
                ON quotation_terms_versions ((status)) WHERE status = 'approved' AND test_configuration = false;
            CREATE OR REPLACE FUNCTION guard_quotation_terms_version() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE' AND OLD.status IN ('approved', 'superseded') THEN
                    RAISE EXCEPTION 'Approved quotation terms cannot be deleted' USING ERRCODE = '23514';
                END IF;
                IF TG_OP = 'UPDATE' AND OLD.status IN ('approved', 'superseded') THEN
                    IF OLD.status = 'superseded' OR NEW.status NOT IN ('approved', 'superseded') THEN
                        RAISE EXCEPTION 'Approved quotation terms cannot return to draft' USING ERRCODE = '23514';
                    END IF;
                    IF (to_jsonb(NEW) - 'status' - 'updated_at') IS DISTINCT FROM (to_jsonb(OLD) - 'status' - 'updated_at') THEN
                        RAISE EXCEPTION 'Approved quotation terms are immutable' USING ERRCODE = '23514';
                    END IF;
                END IF;
                RETURN COALESCE(NEW, OLD);
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER quotation_terms_version_guard
                BEFORE UPDATE OR DELETE ON quotation_terms_versions
                FOR EACH ROW EXECUTE FUNCTION guard_quotation_terms_version();
            SQL);

        foreach ([
            'quotation.view' => 'View quotations',
            'quotation.manage' => 'Create and manage quotation drafts',
            'quotation.issue' => 'Issue standard quotations',
            'quotation.adjustment.request' => 'Request exceptional quotation pricing',
            'quotation.adjustment.approve' => 'Approve exceptional quotation pricing',
            'quotation.acceptance.record' => 'Record offline quotation acceptance',
            'quotation.delivery.record' => 'Record quotation delivery',
        ] as $code => $description) {
            DB::table('permissions')->insertOrIgnore([
                'code' => $code,
                'description' => $description,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (DB::table('quotation_terms_versions')->exists()) {
            throw new RuntimeException('Quotation-term history is immutable and cannot be removed automatically.');
        }

        DB::statement('DROP FUNCTION IF EXISTS guard_quotation_terms_version()');
        Schema::dropIfExists('quotation_terms_versions');
        $permissions = DB::table('permissions')->where('code', 'like', 'quotation.%')->pluck('id');
        if (DB::table('role_permissions')->whereIn('permission_id', $permissions)->exists()
            || DB::table('user_permission_overrides')->whereIn('permission_id', $permissions)->exists()) {
            throw new RuntimeException('Remove assigned quotation permissions before rollback.');
        }
        DB::table('permissions')->whereIn('id', $permissions)->delete();
    }
};
