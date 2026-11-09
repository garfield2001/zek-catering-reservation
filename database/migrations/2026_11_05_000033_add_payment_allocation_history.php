<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_allocations', function (Blueprint $table): void {
            $table->timestampTz('superseded_at')->nullable();
            $table->foreignId('superseded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('supersession_reason')->nullable();
        });
        Schema::table('payment_allocations', function (Blueprint $table): void {
            $table->dropUnique(['payment_id']);
        });
        DB::statement('CREATE UNIQUE INDEX payment_allocations_one_active_payment ON payment_allocations(payment_id) WHERE superseded_at IS NULL');
        DB::statement("CREATE OR REPLACE FUNCTION guard_payment_allocation_history() RETURNS trigger AS $$ BEGIN IF TG_OP = 'DELETE' THEN RAISE EXCEPTION 'payment allocation history is append-only'; END IF; IF OLD.quotation_version_id <> NEW.quotation_version_id OR OLD.amount_minor <> NEW.amount_minor OR OLD.payment_id <> NEW.payment_id THEN RAISE EXCEPTION 'payment allocation facts cannot be edited'; END IF; RETURN NEW; END; $$ LANGUAGE plpgsql");
        DB::statement('CREATE TRIGGER payment_allocations_history_guard BEFORE UPDATE OR DELETE ON payment_allocations FOR EACH ROW EXECUTE FUNCTION guard_payment_allocation_history()');
    }

    public function down(): void
    {
        throw new RuntimeException('Payment allocation history is forward-only.');
    }
};
