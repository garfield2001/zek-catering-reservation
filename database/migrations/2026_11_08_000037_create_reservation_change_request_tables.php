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
            $table->unsignedSmallInteger('minimum_rescheduling_notice_days')->default(14)->after('minimum_advance_booking_days');
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE business_policies DROP CONSTRAINT business_policy_integrity;
            ALTER TABLE business_policies ADD CONSTRAINT business_policy_integrity CHECK (
                key = 'current'
                AND minimum_advance_booking_days BETWEEN 1 AND 365
                AND minimum_rescheduling_notice_days BETWEEN 1 AND 365
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

        Schema::create('reservation_change_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->restrictOnDelete();
            $table->foreignId('quotation_id')->constrained()->restrictOnDelete();
            $table->uuid('idempotency_key')->unique();
            $table->string('status', 32);
            $table->jsonb('change_types');
            $table->jsonb('original_snapshot');
            $table->jsonb('proposed_snapshot');
            $table->jsonb('policy_snapshot');
            $table->text('request_note')->nullable();
            $table->boolean('requested_by_customer')->default(false);
            $table->foreignId('requested_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('requested_at');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->text('exception_reason')->nullable();
            $table->boolean('requires_quotation_revision')->default(false);
            $table->foreignId('quotation_version_id')->nullable()->constrained('quotation_versions')->restrictOnDelete();
            $table->foreignId('applied_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('applied_at')->nullable();
            $table->timestampsTz();
            $table->index(['reservation_id', 'id']);
            $table->index(['quotation_id', 'status']);
        });

        Schema::create('reservation_change_request_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reservation_change_request_id')->constrained()->restrictOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->text('note')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['reservation_change_request_id', 'id']);
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE reservation_change_requests ADD CONSTRAINT reservation_change_request_integrity CHECK (
                status IN ('pending_review', 'awaiting_customer_agreement', 'awaiting_reconfirmation', 'applied', 'declined')
                AND jsonb_typeof(change_types) = 'array'
                AND jsonb_array_length(change_types) > 0
                AND jsonb_typeof(original_snapshot) = 'object'
                AND jsonb_typeof(proposed_snapshot) = 'object'
                AND jsonb_typeof(policy_snapshot) = 'object'
                AND ((requested_by_customer = true AND requested_by IS NULL) OR requested_by_customer = false)
                AND ((status = 'pending_review' AND reviewed_by IS NULL AND reviewed_at IS NULL AND applied_by IS NULL AND applied_at IS NULL)
                    OR (status = 'awaiting_customer_agreement' AND reviewed_by IS NOT NULL AND reviewed_at IS NOT NULL AND applied_by IS NULL AND applied_at IS NULL AND requires_quotation_revision = true AND quotation_version_id IS NOT NULL)
                    OR (status = 'awaiting_reconfirmation' AND reviewed_by IS NOT NULL AND reviewed_at IS NOT NULL AND applied_by IS NULL AND applied_at IS NULL)
                    OR (status = 'applied' AND reviewed_by IS NOT NULL AND reviewed_at IS NOT NULL AND applied_by IS NOT NULL AND applied_at IS NOT NULL)
                    OR (status = 'declined' AND reviewed_by IS NOT NULL AND reviewed_at IS NOT NULL AND applied_by IS NULL AND applied_at IS NULL AND NULLIF(BTRIM(review_note), '') IS NOT NULL))
            );
            CREATE UNIQUE INDEX reservation_change_requests_one_active_per_reservation
                ON reservation_change_requests (reservation_id)
                WHERE status IN ('pending_review', 'awaiting_customer_agreement', 'awaiting_reconfirmation');
            ALTER TABLE reservation_change_request_events ADD CONSTRAINT reservation_change_request_event_integrity CHECK (
                (from_status IS NULL OR from_status IN ('pending_review', 'awaiting_customer_agreement', 'awaiting_reconfirmation', 'applied', 'declined'))
                AND to_status IN ('pending_review', 'awaiting_customer_agreement', 'awaiting_reconfirmation', 'applied', 'declined')
            );
            CREATE OR REPLACE FUNCTION guard_reservation_change_request_event() RETURNS trigger AS $$
            BEGIN
                IF TG_OP <> 'INSERT' THEN
                    RAISE EXCEPTION 'Reservation change-request history is append-only' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER reservation_change_request_events_immutable
                BEFORE UPDATE OR DELETE ON reservation_change_request_events
                FOR EACH ROW EXECUTE FUNCTION guard_reservation_change_request_event();
            SQL);

        DB::table('permissions')->updateOrInsert(
            ['code' => 'reservation.change.manage'],
            ['description' => 'Prepare and submit reservation change requests for review', 'updated_at' => now(), 'created_at' => now()],
        );
    }

    public function down(): void
    {
        throw new RuntimeException('Reservation change history is forward-only.');
    }
};
