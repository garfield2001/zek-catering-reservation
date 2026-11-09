<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_policies', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 32)->unique();
            $table->unsignedSmallInteger('minimum_advance_booking_days')->default(14);
            $table->unsignedSmallInteger('event_duration_hours')->default(4);
            $table->boolean('emergency_booking_requires_administrator')->default(true);
            $table->unsignedSmallInteger('quotation_validity_days')->default(7);
            $table->boolean('allow_quotation_validity_overrides')->default(true);
            $table->string('reservation_payment_type', 16)->default('fixed_amount');
            $table->bigInteger('reservation_payment_amount')->nullable();
            $table->unsignedInteger('reservation_payment_basis_points')->nullable();
            $table->unsignedSmallInteger('reservation_payment_deadline_hours')->default(48);
            $table->unsignedSmallInteger('final_payment_days_before_event')->default(7);
            $table->unsignedSmallInteger('menu_finalization_days_after_acceptance')->default(3);
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
        });

        Schema::table('reservation_requests', function (Blueprint $table): void {
            // Mobile number plus the private access code are the portal
            // credential; an email address is optional for an inquiry.
            $table->string('customer_email')->nullable()->change();
            $table->string('customer_access_code_hash', 255)->nullable()->unique()->after('idempotency_key');
            $table->timestampTz('customer_access_code_reset_at')->nullable()->after('customer_access_code_hash');
            $table->foreignId('customer_access_code_reset_by')->nullable()->after('customer_access_code_reset_at')->constrained('users')->restrictOnDelete();
        });

        Schema::table('quotation_acceptances', function (Blueprint $table): void {
            $table->timestampTz('final_payment_due_at')->nullable()->after('payment_due_at');
        });

        DB::unprepared(<<<'SQL'
            INSERT INTO business_policies (
                key, minimum_advance_booking_days, event_duration_hours,
                emergency_booking_requires_administrator, quotation_validity_days,
                allow_quotation_validity_overrides, reservation_payment_type,
                reservation_payment_amount, reservation_payment_basis_points,
                reservation_payment_deadline_hours, final_payment_days_before_event,
                menu_finalization_days_after_acceptance, created_at, updated_at
            ) VALUES (
                'current', 14, 4, true, 7, true, 'fixed_amount', 0, null, 48, 7, 3, now(), now()
            );

            ALTER TABLE business_policies ADD CONSTRAINT business_policy_integrity CHECK (
                key = 'current'
                AND minimum_advance_booking_days BETWEEN 1 AND 365
                AND event_duration_hours = 4
                AND quotation_validity_days BETWEEN 1 AND 365
                AND reservation_payment_deadline_hours BETWEEN 1 AND 8760
                AND final_payment_days_before_event BETWEEN 0 AND 365
                AND menu_finalization_days_after_acceptance BETWEEN 1 AND 365
                AND ((reservation_payment_type = 'fixed_amount' AND reservation_payment_amount IS NOT NULL AND reservation_payment_amount >= 0 AND reservation_payment_basis_points IS NULL)
                    OR (reservation_payment_type = 'percentage' AND reservation_payment_amount IS NULL AND reservation_payment_basis_points BETWEEN 0 AND 10000))
            );

            ALTER TABLE quotation_versions DROP CONSTRAINT quotation_version_integrity;
            ALTER TABLE quotation_versions ADD CONSTRAINT quotation_version_integrity CHECK (
                status IN ('draft', 'issued', 'accepted', 'withdrawn', 'superseded', 'expired')
                AND subtotal_minor >= 0 AND total_minor >= 0
                AND currency = 'PHP'
                AND ((status IN ('issued', 'accepted', 'withdrawn', 'superseded', 'expired') AND issued_at IS NOT NULL AND issued_by IS NOT NULL AND valid_until IS NOT NULL AND terms_snapshot IS NOT NULL AND public_token_hash IS NOT NULL)
                    OR status = 'draft')
            );
            SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Business policy and customer access history must not be removed automatically.');
    }
};
