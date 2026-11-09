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
            $table->unsignedSmallInteger('cancellation_notice_days')->default(14)->after('minimum_rescheduling_notice_days');
            $table->text('cancellation_refund_guidance')->default('Cancellation and refund requests are reviewed by ZEK Catering. A refund is not promised until it is approved, and is not recorded as completed until the money has actually been returned.')->after('menu_finalization_days_after_acceptance');
        });

        Schema::table('reservations', function (Blueprint $table): void {
            $table->foreignId('cancelled_by')->nullable()->after('completion_note')->constrained('users')->restrictOnDelete();
            $table->timestampTz('cancelled_at')->nullable()->after('cancelled_by');
            $table->text('cancellation_note')->nullable()->after('cancelled_at');
        });

        Schema::create('cancellation_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reservation_request_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('quotation_id')->constrained()->restrictOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->restrictOnDelete();
            $table->uuid('idempotency_key')->unique();
            $table->string('kind', 16);
            $table->string('status', 32);
            $table->text('reason');
            $table->text('internal_note')->nullable();
            $table->jsonb('policy_snapshot');
            $table->jsonb('reservation_snapshot')->nullable();
            $table->jsonb('financial_snapshot');
            $table->boolean('requested_by_customer')->default(false);
            $table->boolean('requested_by_business')->default(false);
            $table->foreignId('requested_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('requested_at');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('finalized_at')->nullable();
            $table->timestampsTz();
            $table->index(['quotation_id', 'status']);
            $table->index(['reservation_id', 'id']);
        });

        Schema::create('cancellation_request_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cancellation_request_id')->constrained()->restrictOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->text('note')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['cancellation_request_id', 'id']);
        });

        Schema::create('refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cancellation_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->uuid('idempotency_key')->unique();
            $table->bigInteger('amount_minor');
            $table->string('status', 24);
            $table->text('reason');
            $table->text('internal_note')->nullable();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('requested_at');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('completed_at')->nullable();
            $table->string('evidence_disk', 32)->nullable();
            $table->string('evidence_path')->nullable();
            $table->char('evidence_sha256', 64)->nullable();
            $table->timestampsTz();
            $table->index(['cancellation_request_id', 'status']);
            $table->index(['payment_id', 'status']);
        });

        Schema::create('refund_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('refund_id')->constrained()->restrictOnDelete();
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->text('note')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['refund_id', 'id']);
        });

        Schema::create('refund_corrections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('refund_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount_minor');
            $table->text('reason');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['refund_id', 'id']);
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE business_policies DROP CONSTRAINT business_policy_integrity;
            ALTER TABLE business_policies ADD CONSTRAINT business_policy_integrity CHECK (
                key = 'current'
                AND minimum_advance_booking_days BETWEEN 1 AND 365
                AND minimum_rescheduling_notice_days BETWEEN 1 AND 365
                AND cancellation_notice_days BETWEEN 1 AND 365
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
                AND btrim(cancellation_refund_guidance) <> ''
                AND ((reservation_payment_type = 'fixed_amount' AND reservation_payment_amount IS NOT NULL AND reservation_payment_amount >= 0 AND reservation_payment_basis_points IS NULL)
                    OR (reservation_payment_type = 'percentage' AND reservation_payment_amount IS NULL AND reservation_payment_basis_points BETWEEN 0 AND 10000))
            );

            ALTER TABLE reservations DROP CONSTRAINT reservation_integrity;
            ALTER TABLE reservations ADD CONSTRAINT reservation_integrity CHECK (
                status IN ('confirmed', 'completed', 'cancelled')
                AND timezone = 'Asia/Manila'
                AND jsonb_typeof(confirmation_snapshot) = 'object'
                AND jsonb_typeof(capacity_snapshot) = 'object'
                AND ((status = 'confirmed' AND completed_by IS NULL AND completed_at IS NULL AND completion_note IS NULL AND cancelled_by IS NULL AND cancelled_at IS NULL AND cancellation_note IS NULL)
                    OR (status = 'completed' AND completed_by IS NOT NULL AND completed_at IS NOT NULL AND cancelled_by IS NULL AND cancelled_at IS NULL AND cancellation_note IS NULL)
                    OR (status = 'cancelled' AND completed_by IS NULL AND completed_at IS NULL AND completion_note IS NULL AND cancelled_by IS NOT NULL AND cancelled_at IS NOT NULL AND NULLIF(BTRIM(cancellation_note), '') IS NOT NULL))
            );
            ALTER TABLE reservation_status_events DROP CONSTRAINT reservation_status_event_integrity;
            ALTER TABLE reservation_status_events ADD CONSTRAINT reservation_status_event_integrity CHECK (
                (from_status IS NULL OR from_status IN ('confirmed', 'completed', 'cancelled'))
                AND to_status IN ('confirmed', 'completed', 'cancelled')
            );

            ALTER TABLE cancellation_requests ADD CONSTRAINT cancellation_request_integrity CHECK (
                kind IN ('withdrawal', 'cancellation')
                AND status IN ('pending_review', 'declined', 'cancelled', 'refund_under_review', 'refund_approved', 'refund_completed')
                AND NULLIF(BTRIM(reason), '') IS NOT NULL
                AND jsonb_typeof(policy_snapshot) = 'object'
                AND jsonb_typeof(financial_snapshot) = 'object'
                AND (reservation_snapshot IS NULL OR jsonb_typeof(reservation_snapshot) = 'object')
                AND ((requested_by_customer = true AND requested_by_business = false AND requested_by IS NULL)
                    OR (requested_by_customer = false AND requested_by IS NOT NULL))
                AND ((kind = 'withdrawal' AND reservation_id IS NULL) OR (kind = 'cancellation' AND reservation_id IS NOT NULL))
                AND ((status = 'pending_review' AND reviewed_by IS NULL AND reviewed_at IS NULL AND finalized_by IS NULL AND finalized_at IS NULL)
                    OR (status = 'declined' AND reviewed_by IS NOT NULL AND reviewed_at IS NOT NULL AND NULLIF(BTRIM(review_note), '') IS NOT NULL AND finalized_by IS NULL AND finalized_at IS NULL)
                    OR (status IN ('cancelled', 'refund_under_review', 'refund_approved', 'refund_completed') AND reviewed_by IS NOT NULL AND reviewed_at IS NOT NULL AND finalized_by IS NOT NULL AND finalized_at IS NOT NULL))
            );
            CREATE UNIQUE INDEX cancellation_requests_one_active_per_quotation
                ON cancellation_requests (quotation_id)
                WHERE status IN ('pending_review', 'refund_under_review', 'refund_approved');
            ALTER TABLE cancellation_request_events ADD CONSTRAINT cancellation_request_event_integrity CHECK (
                (from_status IS NULL OR from_status IN ('pending_review', 'declined', 'cancelled', 'refund_under_review', 'refund_approved', 'refund_completed'))
                AND to_status IN ('pending_review', 'declined', 'cancelled', 'refund_under_review', 'refund_approved', 'refund_completed')
            );
            ALTER TABLE refunds ADD CONSTRAINT refund_integrity CHECK (
                amount_minor > 0
                AND status IN ('pending_review', 'approved', 'rejected', 'completed')
                AND NULLIF(BTRIM(reason), '') IS NOT NULL
                AND ((status = 'pending_review' AND reviewed_by IS NULL AND reviewed_at IS NULL AND completed_by IS NULL AND completed_at IS NULL)
                    OR (status = 'approved' AND reviewed_by IS NOT NULL AND reviewed_at IS NOT NULL AND completed_by IS NULL AND completed_at IS NULL)
                    OR (status = 'rejected' AND reviewed_by IS NOT NULL AND reviewed_at IS NOT NULL AND NULLIF(BTRIM(review_note), '') IS NOT NULL AND completed_by IS NULL AND completed_at IS NULL)
                    OR (status = 'completed' AND reviewed_by IS NOT NULL AND reviewed_at IS NOT NULL AND completed_by IS NOT NULL AND completed_at IS NOT NULL))
            );
            ALTER TABLE refund_events ADD CONSTRAINT refund_event_integrity CHECK (
                (from_status IS NULL OR from_status IN ('pending_review', 'approved', 'rejected', 'completed'))
                AND to_status IN ('pending_review', 'approved', 'rejected', 'completed')
            );
            ALTER TABLE refund_corrections ADD CONSTRAINT refund_correction_integrity CHECK (
                amount_minor <> 0 AND NULLIF(BTRIM(reason), '') IS NOT NULL
            );

            CREATE OR REPLACE FUNCTION validate_cancellation_request_ownership() RETURNS trigger AS $$
            DECLARE request_quotation_id bigint;
            DECLARE reservation_quotation_id bigint;
            BEGIN
                IF NEW.reservation_request_id IS NOT NULL THEN
                    SELECT id INTO request_quotation_id FROM quotations WHERE reservation_request_id = NEW.reservation_request_id AND id = NEW.quotation_id;
                    IF request_quotation_id IS NULL THEN
                        RAISE EXCEPTION 'Cancellation request must belong to the linked inquiry quotation' USING ERRCODE = '23514';
                    END IF;
                END IF;
                IF NEW.reservation_id IS NOT NULL THEN
                    SELECT quotation_id INTO reservation_quotation_id FROM reservations WHERE id = NEW.reservation_id;
                    IF reservation_quotation_id IS NULL OR reservation_quotation_id <> NEW.quotation_id THEN
                        RAISE EXCEPTION 'Cancellation request reservation must belong to its quotation' USING ERRCODE = '23514';
                    END IF;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER cancellation_requests_ownership_guard
                BEFORE INSERT OR UPDATE ON cancellation_requests
                FOR EACH ROW EXECUTE FUNCTION validate_cancellation_request_ownership();

            CREATE OR REPLACE FUNCTION validate_refund_ownership() RETURNS trigger AS $$
            DECLARE cancellation_quotation_id bigint;
            DECLARE payment_quotation_id bigint;
            BEGIN
                SELECT quotation_id INTO cancellation_quotation_id FROM cancellation_requests WHERE id = NEW.cancellation_request_id;
                SELECT quotation_id INTO payment_quotation_id FROM payments WHERE id = NEW.payment_id;
                IF cancellation_quotation_id IS NULL OR payment_quotation_id IS NULL OR cancellation_quotation_id <> payment_quotation_id THEN
                    RAISE EXCEPTION 'Refund payment must belong to the cancellation quotation' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER refunds_ownership_guard
                BEFORE INSERT OR UPDATE ON refunds
                FOR EACH ROW EXECUTE FUNCTION validate_refund_ownership();

            CREATE OR REPLACE FUNCTION guard_cancellation_request_event() RETURNS trigger AS $$
            BEGIN
                IF TG_OP <> 'INSERT' THEN
                    RAISE EXCEPTION 'Cancellation request history is append-only' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER cancellation_request_events_immutable
                BEFORE UPDATE OR DELETE ON cancellation_request_events
                FOR EACH ROW EXECUTE FUNCTION guard_cancellation_request_event();
            CREATE OR REPLACE FUNCTION guard_refund_event() RETURNS trigger AS $$
            BEGIN
                IF TG_OP <> 'INSERT' THEN
                    RAISE EXCEPTION 'Refund history is append-only' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER refund_events_immutable
                BEFORE UPDATE OR DELETE ON refund_events
                FOR EACH ROW EXECUTE FUNCTION guard_refund_event();
            CREATE OR REPLACE FUNCTION guard_refund_correction() RETURNS trigger AS $$
            BEGIN
                IF TG_OP <> 'INSERT' THEN
                    RAISE EXCEPTION 'Refund corrections are append-only' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER refund_corrections_immutable
                BEFORE UPDATE OR DELETE ON refund_corrections
                FOR EACH ROW EXECUTE FUNCTION guard_refund_correction();
            SQL);

        DB::table('permissions')->updateOrInsert(
            ['code' => 'cancellation.manage'],
            ['description' => 'Prepare and view cancellation and refund requests', 'updated_at' => now(), 'created_at' => now()],
        );
    }

    public function down(): void
    {
        throw new RuntimeException('Cancellation and refund history is forward-only.');
    }
};
