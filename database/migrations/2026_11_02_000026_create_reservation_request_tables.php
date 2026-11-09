<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->uuid('idempotency_key')->unique();
            $table->string('status', 24)->default('submitted');
            $table->string('customer_name', 160);
            $table->string('customer_mobile', 40);
            $table->string('customer_email', 254);
            $table->date('event_date');
            $table->time('event_start_time');
            $table->time('event_end_time');
            $table->boolean('event_ends_next_day')->default(false);
            $table->string('timezone', 64)->default('Asia/Manila');
            $table->text('venue_information');
            $table->text('event_notes')->nullable();
            $table->unsignedInteger('guest_count');
            $table->foreignId('main_package_version_id')->constrained('package_versions')->restrictOnDelete();
            $table->bigInteger('estimated_subtotal_minor');
            $table->char('currency', 3)->default('PHP');
            $table->jsonb('commercial_snapshot');
            $table->timestampsTz();
            $table->index(['status', 'event_date', 'event_start_time']);
            $table->index(['main_package_version_id', 'event_date']);
        });

        Schema::create('reservation_request_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reservation_request_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('line_number');
            $table->string('line_type', 16);
            $table->foreignId('package_version_id')->nullable()->constrained('package_versions')->restrictOnDelete();
            $table->foreignId('listing_version_id')->nullable()->constrained('listing_versions')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->bigInteger('estimated_total_minor');
            $table->jsonb('commercial_snapshot');
            $table->timestampsTz();
            $table->unique(['reservation_request_id', 'line_number']);
            $table->index('package_version_id');
            $table->index('listing_version_id');
        });

        Schema::create('reservation_request_status_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reservation_request_id')->constrained()->restrictOnDelete();
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->foreignId('changed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['reservation_request_id', 'id']);
            $table->index('changed_by');
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE reservation_requests ADD CONSTRAINT reservation_request_integrity CHECK (
                status IN ('submitted', 'under_review', 'needs_information', 'closed')
                AND btrim(customer_name) <> '' AND btrim(customer_mobile) <> '' AND btrim(customer_email) <> ''
                AND btrim(venue_information) <> '' AND guest_count > 0
                AND timezone = 'Asia/Manila' AND currency = 'PHP' AND estimated_subtotal_minor >= 0
                AND jsonb_typeof(commercial_snapshot) = 'object'
            );
            ALTER TABLE reservation_request_lines ADD CONSTRAINT reservation_request_line_integrity CHECK (
                line_type IN ('main_package', 'addon_package', 'listing')
                AND quantity > 0 AND estimated_total_minor >= 0 AND jsonb_typeof(commercial_snapshot) = 'object'
                AND ((line_type IN ('main_package', 'addon_package') AND package_version_id IS NOT NULL AND listing_version_id IS NULL)
                    OR (line_type = 'listing' AND listing_version_id IS NOT NULL AND package_version_id IS NULL))
            );
            ALTER TABLE reservation_request_status_events ADD CONSTRAINT reservation_request_status_event_integrity CHECK (
                (from_status IS NULL OR from_status IN ('submitted', 'under_review', 'needs_information', 'closed'))
                AND to_status IN ('submitted', 'under_review', 'needs_information', 'closed')
            );
            CREATE UNIQUE INDEX reservation_request_one_main_package ON reservation_request_lines(reservation_request_id) WHERE line_type = 'main_package';
            CREATE OR REPLACE FUNCTION guard_reservation_request_line() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'UPDATE' OR TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'Submitted request commercial lines are immutable' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER reservation_request_line_immutable BEFORE UPDATE OR DELETE ON reservation_request_lines FOR EACH ROW EXECUTE FUNCTION guard_reservation_request_line();
            CREATE OR REPLACE FUNCTION guard_reservation_request_status_event() RETURNS trigger AS $$
            BEGIN
                IF TG_OP <> 'INSERT' THEN
                    RAISE EXCEPTION 'Request status history is append-only' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER reservation_request_status_event_immutable BEFORE UPDATE OR DELETE ON reservation_request_status_events FOR EACH ROW EXECUTE FUNCTION guard_reservation_request_status_event();
            SQL);

        foreach (['reservation_request.view' => 'View reservation requests', 'reservation_request.manage' => 'Review and update reservation request statuses'] as $code => $description) {
            DB::table('permissions')->insertOrIgnore(['code' => $code, 'description' => $description, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        if (DB::table('reservation_requests')->exists()) {
            throw new RuntimeException('Preserve or export reservation requests before removing this capability.');
        }
        $permissionIds = DB::table('permissions')->where('code', 'like', 'reservation_request.%')->pluck('id');
        if (DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->exists() || DB::table('user_permission_overrides')->whereIn('permission_id', $permissionIds)->exists()) {
            throw new RuntimeException('Remove assigned reservation request permissions before rollback.');
        }
        Schema::dropIfExists('reservation_request_status_events');
        Schema::dropIfExists('reservation_request_lines');
        Schema::dropIfExists('reservation_requests');
        DB::statement('DROP FUNCTION IF EXISTS guard_reservation_request_status_event()');
        DB::statement('DROP FUNCTION IF EXISTS guard_reservation_request_line()');
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
