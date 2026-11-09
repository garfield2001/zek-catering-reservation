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
            $table->unsignedInteger('daily_confirmed_event_capacity')->default(3)->after('event_duration_hours');
            $table->unsignedInteger('daily_guest_capacity')->nullable()->after('daily_confirmed_event_capacity');
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
                AND menu_finalization_days_after_acceptance BETWEEN 1 AND 365
                AND ((reservation_payment_type = 'fixed_amount' AND reservation_payment_amount IS NOT NULL AND reservation_payment_amount >= 0 AND reservation_payment_basis_points IS NULL)
                    OR (reservation_payment_type = 'percentage' AND reservation_payment_amount IS NULL AND reservation_payment_basis_points BETWEEN 0 AND 10000))
            );
            SQL);

        Schema::create('reservation_capacity_overrides', function (Blueprint $table): void {
            $table->id();
            $table->date('event_date')->unique();
            $table->unsignedInteger('maximum_confirmed_events');
            $table->unsignedInteger('maximum_guests')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
        });

        Schema::create('reservations', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->foreignId('quotation_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('confirmed_quotation_version_id')->constrained('quotation_versions')->restrictOnDelete();
            $table->string('status', 16)->default('confirmed');
            $table->date('event_date');
            $table->time('event_start_time')->nullable();
            $table->string('timezone', 64)->default('Asia/Manila');
            $table->text('venue_information')->nullable();
            $table->unsignedInteger('guest_count')->nullable();
            $table->jsonb('confirmation_snapshot');
            $table->jsonb('capacity_snapshot');
            $table->foreignId('confirmed_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('confirmed_at');
            $table->foreignId('completed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('completed_at')->nullable();
            $table->text('completion_note')->nullable();
            $table->timestampsTz();
            $table->index(['event_date', 'status']);
            $table->index('confirmed_quotation_version_id');
        });

        Schema::create('reservation_confirmation_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->restrictOnDelete();
            $table->foreignId('quotation_version_id')->constrained('quotation_versions')->restrictOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('outcome', 16);
            $table->text('reason')->nullable();
            $table->jsonb('payment_snapshot');
            $table->jsonb('capacity_snapshot')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['quotation_id', 'id']);
            $table->index(['quotation_version_id', 'id']);
            $table->index('reservation_id');
        });

        Schema::create('reservation_status_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->restrictOnDelete();
            $table->string('from_status', 16)->nullable();
            $table->string('to_status', 16);
            $table->text('note')->nullable();
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['reservation_id', 'id']);
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE reservation_capacity_overrides ADD CONSTRAINT reservation_capacity_override_integrity CHECK (
                maximum_confirmed_events > 0
                AND (maximum_guests IS NULL OR maximum_guests > 0)
            );
            ALTER TABLE reservations ADD CONSTRAINT reservation_integrity CHECK (
                status IN ('confirmed', 'completed')
                AND timezone = 'Asia/Manila'
                AND jsonb_typeof(confirmation_snapshot) = 'object'
                AND jsonb_typeof(capacity_snapshot) = 'object'
                AND ((status = 'confirmed' AND completed_by IS NULL AND completed_at IS NULL AND completion_note IS NULL)
                    OR (status = 'completed' AND completed_by IS NOT NULL AND completed_at IS NOT NULL))
            );
            ALTER TABLE reservation_confirmation_events ADD CONSTRAINT reservation_confirmation_event_integrity CHECK (
                outcome IN ('confirmed', 'deferred', 'declined')
                AND jsonb_typeof(payment_snapshot) = 'object'
                AND (capacity_snapshot IS NULL OR jsonb_typeof(capacity_snapshot) = 'object')
                AND ((outcome = 'confirmed' AND reservation_id IS NOT NULL AND capacity_snapshot IS NOT NULL)
                    OR (outcome IN ('deferred', 'declined') AND reservation_id IS NULL AND NULLIF(BTRIM(reason), '') IS NOT NULL))
            );
            ALTER TABLE reservation_status_events ADD CONSTRAINT reservation_status_event_integrity CHECK (
                (from_status IS NULL OR from_status IN ('confirmed', 'completed'))
                AND to_status IN ('confirmed', 'completed')
            );

            CREATE OR REPLACE FUNCTION validate_reservation_quotation_version() RETURNS trigger AS $$
            DECLARE version_quotation_id bigint;
            BEGIN
                SELECT quotation_id INTO version_quotation_id FROM quotation_versions WHERE id = NEW.confirmed_quotation_version_id;
                IF version_quotation_id IS NULL OR version_quotation_id <> NEW.quotation_id THEN
                    RAISE EXCEPTION 'A reservation confirmation version must belong to its quotation' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER reservations_quotation_version_guard
                BEFORE INSERT OR UPDATE ON reservations
                FOR EACH ROW EXECUTE FUNCTION validate_reservation_quotation_version();

            CREATE OR REPLACE FUNCTION validate_reservation_confirmation_event() RETURNS trigger AS $$
            DECLARE version_quotation_id bigint;
            DECLARE reservation_quotation_id bigint;
            BEGIN
                SELECT quotation_id INTO version_quotation_id FROM quotation_versions WHERE id = NEW.quotation_version_id;
                IF version_quotation_id IS NULL OR version_quotation_id <> NEW.quotation_id THEN
                    RAISE EXCEPTION 'A confirmation event version must belong to its quotation' USING ERRCODE = '23514';
                END IF;
                IF NEW.reservation_id IS NOT NULL THEN
                    SELECT quotation_id INTO reservation_quotation_id FROM reservations WHERE id = NEW.reservation_id;
                    IF reservation_quotation_id IS NULL OR reservation_quotation_id <> NEW.quotation_id THEN
                        RAISE EXCEPTION 'A confirmation event reservation must belong to its quotation' USING ERRCODE = '23514';
                    END IF;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER reservation_confirmation_events_ownership_guard
                BEFORE INSERT OR UPDATE ON reservation_confirmation_events
                FOR EACH ROW EXECUTE FUNCTION validate_reservation_confirmation_event();

            CREATE OR REPLACE FUNCTION guard_reservation_confirmation_event() RETURNS trigger AS $$
            BEGIN
                IF TG_OP <> 'INSERT' THEN
                    RAISE EXCEPTION 'Reservation confirmation history is append-only' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER reservation_confirmation_events_immutable
                BEFORE UPDATE OR DELETE ON reservation_confirmation_events
                FOR EACH ROW EXECUTE FUNCTION guard_reservation_confirmation_event();

            CREATE OR REPLACE FUNCTION guard_reservation_status_event() RETURNS trigger AS $$
            BEGIN
                IF TG_OP <> 'INSERT' THEN
                    RAISE EXCEPTION 'Reservation status history is append-only' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER reservation_status_events_immutable
                BEFORE UPDATE OR DELETE ON reservation_status_events
                FOR EACH ROW EXECUTE FUNCTION guard_reservation_status_event();
            SQL);

        DB::table('permissions')->updateOrInsert(
            ['code' => 'reservation.view'],
            ['description' => 'View reservation confirmation and availability information', 'updated_at' => now(), 'created_at' => now()],
        );
    }

    public function down(): void
    {
        throw new RuntimeException('Reservation confirmation history and capacity decisions are forward-only.');
    }
};
