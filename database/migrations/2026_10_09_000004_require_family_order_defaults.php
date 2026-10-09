<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION require_family_order_default() RETURNS trigger AS $$
            DECLARE
                checked_family_id bigint;
            BEGIN
                IF TG_TABLE_NAME = 'product_families' THEN
                    checked_family_id := NEW.id;
                ELSE
                    checked_family_id := OLD.family_id;
                END IF;

                IF EXISTS (SELECT 1 FROM product_families WHERE id = checked_family_id)
                   AND NOT EXISTS (SELECT 1 FROM family_order_defaults WHERE family_id = checked_family_id) THEN
                    RAISE EXCEPTION 'Product family % requires an ordering default', checked_family_id
                        USING ERRCODE = '23514';
                END IF;

                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;

            CREATE CONSTRAINT TRIGGER product_family_requires_order_default
                AFTER INSERT OR UPDATE ON product_families
                DEFERRABLE INITIALLY DEFERRED
                FOR EACH ROW EXECUTE FUNCTION require_family_order_default();

            CREATE CONSTRAINT TRIGGER family_order_default_cannot_leave_gap
                AFTER DELETE OR UPDATE OF family_id ON family_order_defaults
                DEFERRABLE INITIALLY DEFERRED
                FOR EACH ROW EXECUTE FUNCTION require_family_order_default();
            SQL);

        // An existing installation must already satisfy the invariant before this migration succeeds.
        DB::statement('SET CONSTRAINTS product_family_requires_order_default, family_order_default_cannot_leave_gap IMMEDIATE');
        if (DB::table('product_families')->leftJoin('family_order_defaults', 'product_families.id', '=', 'family_order_defaults.family_id')
            ->whereNull('family_order_defaults.family_id')->exists()) {
            throw new RuntimeException('Every product family must have an ordering default before this migration can be applied.');
        }
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS family_order_default_cannot_leave_gap ON family_order_defaults;
            DROP TRIGGER IF EXISTS product_family_requires_order_default ON product_families;
            DROP FUNCTION IF EXISTS require_family_order_default();
            SQL);
    }
};
