<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pencil_bookings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('idempotency_key')->unique();
            $table->string('status', 24)->default('active');
            $table->string('customer_name', 160);
            $table->string('customer_mobile', 40)->nullable();
            $table->string('customer_email', 254)->nullable();
            $table->date('event_date');
            $table->time('event_start_time')->nullable();
            $table->text('venue_information')->nullable();
            $table->unsignedInteger('guest_count')->nullable();
            $table->text('notes')->nullable();
            $table->date('follow_up_on')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('converted_reservation_request_id')->nullable()->unique()->constrained('reservation_requests')->restrictOnDelete();
            $table->timestampTz('converted_at')->nullable();
            $table->timestampsTz();
            $table->index(['status', 'event_date']);
            $table->index('follow_up_on');
        });
        Schema::create('pencil_booking_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pencil_booking_id')->constrained()->restrictOnDelete();
            $table->string('event_type', 32);
            $table->jsonb('details')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['pencil_booking_id', 'id']);
        });
        Schema::table('reservation_requests', function (Blueprint $table): void {
            $table->string('origin', 24)->default('public')->after('idempotency_key');
            $table->foreignId('pencil_booking_id')->nullable()->unique()->after('origin')->constrained('pencil_bookings')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->after('pencil_booking_id')->constrained('users')->restrictOnDelete();
        });
        DB::unprepared(<<<'SQL'
            ALTER TABLE pencil_bookings ADD CONSTRAINT pencil_booking_integrity CHECK (
                status IN ('active', 'closed', 'did_not_proceed', 'converted')
                AND btrim(customer_name) <> ''
                AND (NULLIF(btrim(customer_mobile), '') IS NOT NULL OR NULLIF(btrim(customer_email), '') IS NOT NULL)
                AND (guest_count IS NULL OR guest_count > 0)
                AND ((status = 'converted' AND converted_reservation_request_id IS NOT NULL AND converted_at IS NOT NULL)
                    OR (status <> 'converted' AND converted_reservation_request_id IS NULL AND converted_at IS NULL))
            );
            ALTER TABLE reservation_requests ADD CONSTRAINT reservation_request_origin_integrity CHECK (
                origin IN ('public', 'manual', 'pencil_booking')
                AND ((origin = 'pencil_booking' AND pencil_booking_id IS NOT NULL AND created_by IS NOT NULL)
                    OR (origin = 'manual' AND pencil_booking_id IS NULL AND created_by IS NOT NULL)
                    OR (origin = 'public' AND pencil_booking_id IS NULL AND created_by IS NULL))
            );
            CREATE OR REPLACE FUNCTION guard_pencil_booking_event() RETURNS trigger AS $$
            BEGIN
                IF TG_OP <> 'INSERT' THEN RAISE EXCEPTION 'Pencil booking history is append-only' USING ERRCODE = '23514'; END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER pencil_booking_events_immutable BEFORE UPDATE OR DELETE ON pencil_booking_events FOR EACH ROW EXECUTE FUNCTION guard_pencil_booking_event();
            SQL);
        foreach (['pencil_booking.view' => 'View pencil bookings', 'pencil_booking.manage' => 'Create, update, close and convert pencil bookings'] as $code => $description) {
            DB::table('permissions')->insertOrIgnore(['code' => $code, 'description' => $description, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Pencil booking history is forward-only.');
    }
};
