<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('instructions')->nullable();
            $table->text('destination_information')->nullable();
            $table->boolean('is_offline')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['name']);
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quotation_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('reservation_request_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->string('status');
            $table->string('source');
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3)->default('PHP');
            $table->timestampTz('paid_at');
            $table->string('transaction_reference')->nullable();
            $table->text('notes')->nullable();
            $table->string('proof_disk')->nullable();
            $table->string('proof_path')->nullable();
            $table->string('proof_mime')->nullable();
            $table->string('proof_sha256', 64)->nullable();
            $table->uuid('idempotency_key')->unique();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('rejected_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->foreignId('corrects_payment_id')->nullable()->constrained('payments')->restrictOnDelete();
            $table->text('exception_reason')->nullable();
            $table->timestamps();
            $table->index(['quotation_id', 'status']);
            $table->index(['reservation_request_id', 'status']);
            $table->index(['status', 'paid_at']);
        });

        Schema::create('payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('quotation_version_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->foreignId('allocated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->unique('payment_id');
        });

        DB::statement("ALTER TABLE payments ADD CONSTRAINT payment_integrity CHECK (status IN ('pending_verification','verified','rejected','reversed') AND source IN ('customer_submission','staff_recorded','administrator_exception') AND amount_minor > 0 AND currency = 'PHP' AND ((proof_disk IS NULL AND proof_path IS NULL AND proof_mime IS NULL AND proof_sha256 IS NULL) OR (proof_disk IS NOT NULL AND proof_path IS NOT NULL AND proof_mime IS NOT NULL AND proof_sha256 IS NOT NULL)) AND (status <> 'rejected' OR rejection_reason IS NOT NULL) AND (status <> 'reversed' OR reversal_reason IS NOT NULL))");
        DB::statement("CREATE UNIQUE INDEX payments_unique_transaction_reference ON payments(payment_method_id, transaction_reference) WHERE transaction_reference IS NOT NULL AND status <> 'rejected'");
        DB::statement("CREATE UNIQUE INDEX payments_unique_proof_hash ON payments(proof_sha256) WHERE proof_sha256 IS NOT NULL AND status <> 'rejected'");
        DB::statement('ALTER TABLE payment_allocations ADD CONSTRAINT payment_allocation_amount CHECK (amount_minor > 0)');
        DB::statement("CREATE OR REPLACE FUNCTION guard_payment_history() RETURNS trigger AS $$ BEGIN IF TG_OP = 'DELETE' THEN RAISE EXCEPTION 'payment records are append-only financial history'; END IF; IF OLD.status IN ('verified','reversed') AND (NEW.amount_minor <> OLD.amount_minor OR NEW.payment_method_id <> OLD.payment_method_id OR NEW.paid_at <> OLD.paid_at OR NEW.transaction_reference IS DISTINCT FROM OLD.transaction_reference OR NEW.proof_path IS DISTINCT FROM OLD.proof_path) THEN RAISE EXCEPTION 'verified payment facts cannot be edited'; END IF; RETURN NEW; END; $$ LANGUAGE plpgsql");
        DB::statement('CREATE TRIGGER payments_history_guard BEFORE UPDATE OR DELETE ON payments FOR EACH ROW EXECUTE FUNCTION guard_payment_history()');

        foreach (['payment.view' => 'View payment records', 'payment.record' => 'Record received payments for Administrator verification', 'payment.verify' => 'Verify, reject and reverse payments'] as $code => $description) {
            DB::table('permissions')->updateOrInsert(['code' => $code], ['description' => $description, 'updated_at' => now(), 'created_at' => now()]);
        }
        foreach (['gcash' => 'GCash', 'maya' => 'Maya', 'paypal' => 'PayPal', 'bank_transfer' => 'Bank transfer', 'cash' => 'Cash', 'other' => 'Other'] as $code => $name) {
            DB::table('payment_methods')->insert(['code' => $code, 'name' => $name, 'is_offline' => in_array($code, ['cash', 'other'], true), 'is_active' => false, 'display_order' => array_search($code, array_keys(['gcash' => 'GCash', 'maya' => 'Maya', 'paypal' => 'PayPal', 'bank_transfer' => 'Bank transfer', 'cash' => 'Cash', 'other' => 'Other']), true), 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Payment financial records are intentionally forward-only.');
    }
};
