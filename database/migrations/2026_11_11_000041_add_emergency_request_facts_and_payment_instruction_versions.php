<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_requests', function (Blueprint $table): void {
            $table->boolean('emergency_booking_exception')->default(false)->after('origin');
            $table->text('emergency_booking_reason')->nullable()->after('emergency_booking_exception');
            $table->foreignId('emergency_booking_authorized_by')->nullable()->after('emergency_booking_reason')->constrained('users')->restrictOnDelete();
            $table->jsonb('emergency_booking_policy_snapshot')->nullable()->after('emergency_booking_authorized_by');
        });

        Schema::create('payment_instruction_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_policy_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('account_name', 255);
            $table->string('ewallet_number', 255);
            $table->string('bank_account', 255);
            $table->text('additional_instructions');
            $table->timestampTz('effective_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['business_policy_id', 'version_number']);
            $table->index(['business_policy_id', 'effective_at']);
        });

        Schema::table('business_policies', function (Blueprint $table): void {
            $table->foreignId('current_payment_instruction_version_id')->nullable()->after('payment_additional_instructions')->constrained('payment_instruction_versions')->restrictOnDelete();
        });

        DB::unprepared(<<<'SQL'
            INSERT INTO payment_instruction_versions (
                business_policy_id, version_number, account_name, ewallet_number,
                bank_account, additional_instructions, effective_at, created_by, created_at
            )
            SELECT id, 1, payment_account_name, payment_ewallet_number,
                payment_bank_account, payment_additional_instructions, updated_at,
                updated_by, now()
            FROM business_policies
            WHERE key = 'current';

            UPDATE business_policies policy
            SET current_payment_instruction_version_id = version.id
            FROM payment_instruction_versions version
            WHERE version.business_policy_id = policy.id AND version.version_number = 1;

            ALTER TABLE business_policies
                ALTER COLUMN current_payment_instruction_version_id SET NOT NULL;

            ALTER TABLE reservation_requests ADD CONSTRAINT reservation_request_emergency_booking_integrity CHECK (
                (emergency_booking_exception = false
                    AND emergency_booking_reason IS NULL
                    AND emergency_booking_authorized_by IS NULL
                    AND emergency_booking_policy_snapshot IS NULL)
                OR (emergency_booking_exception = true
                    AND btrim(emergency_booking_reason) <> ''
                    AND emergency_booking_authorized_by IS NOT NULL
                    AND jsonb_typeof(emergency_booking_policy_snapshot) = 'object')
            );

            CREATE OR REPLACE FUNCTION guard_reservation_request_emergency_booking_facts() RETURNS trigger AS $$
            BEGIN
                IF OLD.emergency_booking_exception IS DISTINCT FROM NEW.emergency_booking_exception
                    OR OLD.emergency_booking_reason IS DISTINCT FROM NEW.emergency_booking_reason
                    OR OLD.emergency_booking_authorized_by IS DISTINCT FROM NEW.emergency_booking_authorized_by
                    OR OLD.emergency_booking_policy_snapshot IS DISTINCT FROM NEW.emergency_booking_policy_snapshot THEN
                    RAISE EXCEPTION 'Emergency booking authorization facts are immutable' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER reservation_request_emergency_booking_facts_immutable
                BEFORE UPDATE ON reservation_requests
                FOR EACH ROW EXECUTE FUNCTION guard_reservation_request_emergency_booking_facts();

            CREATE OR REPLACE FUNCTION guard_payment_instruction_version() RETURNS trigger AS $$
            BEGIN
                IF TG_OP <> 'INSERT' THEN
                    RAISE EXCEPTION 'Payment instruction history is immutable' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER payment_instruction_versions_immutable
                BEFORE UPDATE OR DELETE ON payment_instruction_versions
                FOR EACH ROW EXECUTE FUNCTION guard_payment_instruction_version();
            SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Emergency booking facts and payment instruction history are forward-only.');
    }
};
