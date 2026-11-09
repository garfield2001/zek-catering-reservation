<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE OR REPLACE FUNCTION guard_payment_allocation_history() RETURNS trigger AS $$ BEGIN IF TG_OP = 'DELETE' THEN RAISE EXCEPTION 'payment allocation history is append-only'; END IF; IF OLD.quotation_version_id <> NEW.quotation_version_id OR OLD.amount_minor <> NEW.amount_minor OR OLD.payment_id <> NEW.payment_id THEN RAISE EXCEPTION 'payment allocation facts cannot be edited'; END IF; IF OLD.superseded_at IS NOT NULL AND (NEW.superseded_at IS DISTINCT FROM OLD.superseded_at OR NEW.superseded_by IS DISTINCT FROM OLD.superseded_by OR NEW.supersession_reason IS DISTINCT FROM OLD.supersession_reason) THEN RAISE EXCEPTION 'a superseded payment allocation cannot be changed'; END IF; IF OLD.superseded_at IS NULL AND NEW.superseded_at IS NOT NULL AND (NEW.superseded_by IS NULL OR NULLIF(BTRIM(NEW.supersession_reason), '') IS NULL) THEN RAISE EXCEPTION 'a superseded payment allocation requires an actor and reason'; END IF; RETURN NEW; END; $$ LANGUAGE plpgsql");
        DB::statement("CREATE OR REPLACE FUNCTION validate_payment_allocation_ownership() RETURNS trigger AS $$ DECLARE payment_quotation_id bigint; version_quotation_id bigint; BEGIN SELECT quotation_id INTO payment_quotation_id FROM payments WHERE id = NEW.payment_id; SELECT quotation_id INTO version_quotation_id FROM quotation_versions WHERE id = NEW.quotation_version_id; IF payment_quotation_id IS NULL OR payment_quotation_id <> version_quotation_id THEN RAISE EXCEPTION 'payment allocations must belong to the payment quotation'; END IF; RETURN NEW; END; $$ LANGUAGE plpgsql");
        DB::statement('CREATE TRIGGER payment_allocations_ownership_guard BEFORE INSERT OR UPDATE ON payment_allocations FOR EACH ROW EXECUTE FUNCTION validate_payment_allocation_ownership()');
    }

    public function down(): void
    {
        throw new RuntimeException('Payment allocation integrity is forward-only.');
    }
};
