<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_policies', function (Blueprint $table): void {
            $table->unsignedSmallInteger('customer_payment_upload_cutoff_days')->default(7)->after('final_payment_days_before_event');
            $table->string('payment_account_name', 255)->default('SAMPLE — ZEK Catering')->after('customer_payment_upload_cutoff_days');
            $table->string('payment_ewallet_number', 255)->default('REPLACE WITH ACTUAL NUMBER')->after('payment_account_name');
            $table->string('payment_bank_account', 255)->default('REPLACE WITH ACTUAL ACCOUNT')->after('payment_ewallet_number');
            $table->text('payment_additional_instructions')->default('Replace these sample details before collecting real payments.')->after('payment_bank_account');
        });
        DB::unprepared(<<<'SQL'
            ALTER TABLE business_policies DROP CONSTRAINT business_policy_integrity;
            ALTER TABLE business_policies ADD CONSTRAINT business_policy_integrity CHECK (
                key = 'current'
                AND minimum_advance_booking_days BETWEEN 1 AND 365
                AND event_duration_hours = 4
                AND daily_confirmed_event_capacity > 0
                AND (daily_guest_capacity IS NULL OR daily_guest_capacity > 0)
                AND quotation_validity_days BETWEEN 1 AND 365
                AND reservation_payment_deadline_hours BETWEEN 1 AND 8760
                AND final_payment_days_before_event BETWEEN 0 AND 365
                AND customer_payment_upload_cutoff_days BETWEEN 0 AND 365
                AND menu_finalization_days_after_acceptance BETWEEN 1 AND 365
                AND btrim(payment_account_name) <> ''
                AND btrim(payment_ewallet_number) <> ''
                AND btrim(payment_bank_account) <> ''
                AND btrim(payment_additional_instructions) <> ''
                AND ((reservation_payment_type = 'fixed_amount' AND reservation_payment_amount IS NOT NULL AND reservation_payment_amount >= 0 AND reservation_payment_basis_points IS NULL)
                    OR (reservation_payment_type = 'percentage' AND reservation_payment_amount IS NULL AND reservation_payment_basis_points BETWEEN 0 AND 10000))
            );
            SQL);

        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('submitted_quotation_version_id')->nullable()->after('quotation_id')->constrained('quotation_versions')->restrictOnDelete();
            $table->text('customer_message')->nullable()->after('notes');
            $table->foreignId('payment_method_id')->nullable()->change();
            $table->unsignedBigInteger('amount_minor')->nullable()->change();
            $table->timestampTz('paid_at')->nullable()->change();
        });
        Schema::create('payment_evidence_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('version_number');
            $table->string('disk', 32);
            $table->string('path');
            $table->string('mime', 128);
            $table->string('sha256', 64)->unique();
            $table->timestampTz('submitted_at');
            $table->timestampsTz();
            $table->unique(['payment_id', 'version_number']);
            $table->index(['payment_id', 'id']);
        });
        DB::unprepared(<<<'SQL'
            ALTER TABLE payments DROP CONSTRAINT payment_integrity;
            ALTER TABLE payments ADD CONSTRAINT payment_integrity CHECK (
                status IN ('pending_verification','verified','rejected','reversed')
                AND source IN ('customer_submission','staff_recorded','administrator_exception')
                AND (amount_minor IS NULL OR amount_minor > 0)
                AND currency = 'PHP'
                AND ((proof_disk IS NULL AND proof_path IS NULL AND proof_mime IS NULL AND proof_sha256 IS NULL)
                    OR (proof_disk IS NOT NULL AND proof_path IS NOT NULL AND proof_mime IS NOT NULL AND proof_sha256 IS NOT NULL))
                AND (status <> 'verified' OR amount_minor IS NOT NULL)
                AND (status <> 'rejected' OR rejection_reason IS NOT NULL)
                AND (status <> 'reversed' OR reversal_reason IS NOT NULL)
            );
            INSERT INTO payment_evidence_versions (payment_id, version_number, disk, path, mime, sha256, submitted_at, created_at, updated_at)
            SELECT id, 1, proof_disk, proof_path, proof_mime, proof_sha256, created_at, created_at, created_at
            FROM payments WHERE proof_path IS NOT NULL;
            CREATE OR REPLACE FUNCTION validate_payment_submission_ownership() RETURNS trigger AS $$
            DECLARE version_quotation_id bigint;
            BEGIN
                IF NEW.submitted_quotation_version_id IS NULL THEN
                    RETURN NEW;
                END IF;
                SELECT quotation_id INTO version_quotation_id FROM quotation_versions WHERE id = NEW.submitted_quotation_version_id;
                IF version_quotation_id IS NULL OR NEW.quotation_id IS NULL OR version_quotation_id <> NEW.quotation_id THEN
                    RAISE EXCEPTION 'A submitted payment version must belong to its quotation' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER payments_submission_ownership_guard
                BEFORE INSERT OR UPDATE ON payments FOR EACH ROW EXECUTE FUNCTION validate_payment_submission_ownership();
            CREATE OR REPLACE FUNCTION guard_payment_evidence_history() RETURNS trigger AS $$
            BEGIN
                IF TG_OP <> 'INSERT' THEN
                    RAISE EXCEPTION 'Payment evidence history is append-only' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER payment_evidence_versions_immutable
                BEFORE UPDATE OR DELETE ON payment_evidence_versions FOR EACH ROW EXECUTE FUNCTION guard_payment_evidence_history();
            SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Customer payment evidence and business-policy history are forward-only.');
    }
};
