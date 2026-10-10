<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_families', function (Blueprint $table) {
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->index(['sort_order', 'id']);
        });
        DB::statement("UPDATE product_families SET sort_order = CASE code WHEN 'main_catering' THEN 10 WHEN 'belly_lechon' THEN 20 WHEN 'food_pack' THEN 30 WHEN 'food_tray' THEN 40 WHEN 'pica_pica' THEN 50 ELSE 0 END");
        DB::statement('ALTER TABLE product_families ADD CONSTRAINT product_families_name_nonempty CHECK (length(btrim(name)) > 0)');
        DB::statement('CREATE UNIQUE INDEX product_families_name_key_unique ON product_families (lower(btrim(name)))');

        Schema::table('products', function (Blueprint $table) {
            $table->integer('sort_order')->default(0);
            $table->index(['family_id', 'sort_order', 'id']);
        });
        Schema::table('product_variants', function (Blueprint $table) {
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->index(['product_id', 'sort_order', 'id']);
        });
        DB::statement('CREATE UNIQUE INDEX product_variants_product_name_key_unique ON product_variants (product_id, lower(btrim(name)))');

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION guard_product_family_identity() RETURNS trigger AS $$
            BEGIN
                IF NEW.code IS DISTINCT FROM OLD.code OR NEW.is_addon IS DISTINCT FROM OLD.is_addon THEN
                    RAISE EXCEPTION 'Product family code and primary/add-on identity are immutable' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER product_family_identity_guard BEFORE UPDATE ON product_families
                FOR EACH ROW EXECUTE FUNCTION guard_product_family_identity();
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS product_family_identity_guard ON product_families');
        DB::statement('DROP FUNCTION IF EXISTS guard_product_family_identity()');
        DB::statement('DROP INDEX IF EXISTS product_variants_product_name_key_unique');
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropIndex(['product_id', 'sort_order', 'id']);
            $table->dropColumn(['description', 'sort_order']);
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['family_id', 'sort_order', 'id']);
            $table->dropColumn('sort_order');
        });
        DB::statement('DROP INDEX IF EXISTS product_families_name_key_unique');
        DB::statement('ALTER TABLE product_families DROP CONSTRAINT IF EXISTS product_families_name_nonempty');
        Schema::table('product_families', function (Blueprint $table) {
            $table->dropIndex(['sort_order', 'id']);
            $table->dropColumn(['description', 'sort_order']);
        });
    }
};
