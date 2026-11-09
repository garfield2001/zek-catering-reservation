<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->string('origin', 24);
            $table->foreignId('reservation_request_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 24)->default('draft');
            $table->string('customer_name');
            $table->string('customer_mobile', 32);
            $table->string('customer_email')->nullable();
            $table->date('event_date')->nullable();
            $table->time('event_start_time')->nullable();
            $table->text('venue_information')->nullable();
            $table->text('event_notes')->nullable();
            $table->jsonb('source_snapshot')->nullable();
            $table->foreignId('current_version_id')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
            $table->index(['reservation_request_id', 'id']);
            $table->index(['status', 'id']);
        });

        Schema::create('quotation_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('status', 24)->default('draft');
            $table->jsonb('line_snapshots');
            $table->bigInteger('subtotal_minor');
            $table->bigInteger('adjustment_minor')->default(0);
            $table->bigInteger('total_minor');
            $table->string('currency', 3)->default('PHP');
            $table->timestampTz('valid_until')->nullable();
            $table->foreignId('terms_version_id')->nullable()->constrained('quotation_terms_versions')->restrictOnDelete();
            $table->jsonb('terms_snapshot')->nullable();
            $table->string('public_token_hash', 64)->nullable()->unique();
            $table->timestampTz('issued_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('withdrawn_at')->nullable();
            $table->foreignId('withdrawn_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('withdrawal_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
            $table->unique(['quotation_id', 'version_number']);
            $table->index(['quotation_id', 'status']);
            $table->index(['status', 'valid_until']);
        });

        Schema::table('quotations', function (Blueprint $table): void {
            $table->foreign('current_version_id')->references('id')->on('quotation_versions')->restrictOnDelete();
        });

        Schema::create('quotation_adjustment_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quotation_version_id')->constrained()->restrictOnDelete();
            $table->bigInteger('requested_adjustment_minor');
            $table->text('reason');
            $table->string('status', 24)->default('pending');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestampsTz();
            $table->index(['quotation_version_id', 'status']);
        });

        Schema::create('quotation_acceptances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quotation_version_id')->unique()->constrained()->restrictOnDelete();
            $table->string('method', 16);
            $table->string('accepted_by_name');
            $table->timestampTz('accepted_at');
            $table->timestampTz('payment_due_at');
            $table->timestampTz('menu_finalization_due_at');
            $table->jsonb('terms_snapshot');
            $table->string('verification_reference', 128)->nullable();
            $table->string('evidence_disk', 32)->nullable();
            $table->string('evidence_path')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
        });

        Schema::create('quotation_delivery_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quotation_version_id')->constrained()->restrictOnDelete();
            $table->string('channel', 24);
            $table->string('destination_hint', 255)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
            $table->index(['quotation_version_id', 'id']);
        });

        Schema::create('quotation_verification_challenges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quotation_version_id')->constrained()->restrictOnDelete();
            $table->string('contact_hint', 64);
            $table->string('code_hash', 255);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('expires_at');
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampTz('verified_until')->nullable();
            $table->timestampsTz();
            $table->index(['quotation_version_id', 'expires_at']);
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE quotations ADD CONSTRAINT quotation_integrity CHECK (
                origin IN ('reservation_request', 'manual')
                AND status IN ('draft', 'issued', 'accepted', 'withdrawn', 'expired')
                AND ((origin = 'reservation_request' AND reservation_request_id IS NOT NULL) OR (origin = 'manual' AND reservation_request_id IS NULL))
            );
            ALTER TABLE quotation_versions ADD CONSTRAINT quotation_version_integrity CHECK (
                status IN ('draft', 'issued', 'accepted', 'withdrawn', 'superseded', 'expired')
                AND subtotal_minor >= 0 AND total_minor >= 0
                AND currency = 'PHP'
                AND ((status IN ('issued', 'accepted', 'withdrawn', 'superseded', 'expired') AND issued_at IS NOT NULL AND issued_by IS NOT NULL AND valid_until IS NOT NULL AND terms_version_id IS NOT NULL AND terms_snapshot IS NOT NULL AND public_token_hash IS NOT NULL)
                    OR status = 'draft')
            );
            ALTER TABLE quotation_adjustment_requests ADD CONSTRAINT quotation_adjustment_integrity CHECK (
                status IN ('pending', 'approved', 'declined')
                AND ((status = 'pending' AND reviewed_by IS NULL AND reviewed_at IS NULL) OR (status IN ('approved', 'declined') AND reviewed_by IS NOT NULL AND reviewed_at IS NOT NULL))
            );
            ALTER TABLE quotation_acceptances ADD CONSTRAINT quotation_acceptance_integrity CHECK (
                method IN ('online', 'offline')
                AND ((method = 'online' AND verification_reference IS NOT NULL) OR (method = 'offline' AND recorded_by IS NOT NULL))
            );
            CREATE OR REPLACE FUNCTION guard_quotation_version_history() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE' AND OLD.status <> 'draft' THEN
                    RAISE EXCEPTION 'Issued quotation versions cannot be deleted' USING ERRCODE = '23514';
                END IF;
                IF TG_OP = 'UPDATE' AND OLD.status <> 'draft' THEN
                    IF OLD.status = 'issued' AND NEW.status = 'withdrawn' THEN
                        IF (to_jsonb(NEW) - ARRAY['status', 'updated_at', 'withdrawn_at', 'withdrawn_by', 'withdrawal_reason'])
                            IS DISTINCT FROM (to_jsonb(OLD) - ARRAY['status', 'updated_at', 'withdrawn_at', 'withdrawn_by', 'withdrawal_reason']) THEN
                            RAISE EXCEPTION 'Issued quotation versions are immutable' USING ERRCODE = '23514';
                        END IF;
                    ELSIF (to_jsonb(NEW) - 'status' - 'updated_at') IS DISTINCT FROM (to_jsonb(OLD) - 'status' - 'updated_at') THEN
                        RAISE EXCEPTION 'Issued quotation versions are immutable' USING ERRCODE = '23514';
                    END IF;
                    IF OLD.status IN ('accepted', 'withdrawn', 'expired', 'superseded') AND NEW.status <> OLD.status THEN
                        RAISE EXCEPTION 'Final quotation-version status cannot change' USING ERRCODE = '23514';
                    END IF;
                END IF;
                RETURN COALESCE(NEW, OLD);
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER quotation_version_history_guard
                BEFORE UPDATE OR DELETE ON quotation_versions
                FOR EACH ROW EXECUTE FUNCTION guard_quotation_version_history();
            SQL);
    }

    public function down(): void
    {
        if (DB::table('quotation_versions')->where('status', '<>', 'draft')->exists()) {
            throw new RuntimeException('Issued quotation history is immutable and cannot be removed automatically.');
        }
        DB::statement('DROP FUNCTION IF EXISTS guard_quotation_version_history()');
        Schema::dropIfExists('quotation_verification_challenges');
        Schema::dropIfExists('quotation_delivery_events');
        Schema::dropIfExists('quotation_acceptances');
        Schema::dropIfExists('quotation_adjustment_requests');
        Schema::table('quotations', fn (Blueprint $table) => $table->dropForeign(['current_version_id']));
        Schema::dropIfExists('quotation_versions');
        Schema::dropIfExists('quotations');
    }
};
