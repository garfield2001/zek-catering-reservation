<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_versions', function (Blueprint $table): void {
            $table->decimal('default_serving_quantity', 12, 3)->nullable();
            $table->foreignId('default_serving_unit_id')->nullable()->constrained('measurement_units')->restrictOnDelete();
            $table->string('default_serving_size_label', 100)->nullable();
            $table->index('default_serving_unit_id');
        });
        Schema::table('package_food_size_choices', function (Blueprint $table): void {
            $table->string('size_label', 100)->nullable();
            $table->dropUnique(['size_group_id', 'quantity']);
        });
        DB::unprepared(<<<'SQL'
            ALTER TABLE package_versions ADD CONSTRAINT package_serving_default_complete CHECK (
                (default_serving_quantity IS NULL AND default_serving_unit_id IS NULL AND default_serving_size_label IS NULL)
                OR (default_serving_quantity IS NOT NULL AND default_serving_quantity > 0 AND default_serving_quantity <= 999999999.999 AND default_serving_unit_id IS NOT NULL
                    AND (default_serving_size_label IS NULL OR length(btrim(default_serving_size_label)) > 0))
            );
            ALTER TABLE package_food_size_choices ADD CONSTRAINT package_food_size_label_valid CHECK (size_label IS NULL OR length(btrim(size_label)) > 0);
            ALTER TABLE package_food_size_choices ADD CONSTRAINT package_food_size_quantity_finite CHECK (quantity <= 999999999.999);
            CREATE UNIQUE INDEX package_food_size_numeric_unique ON package_food_size_choices (size_group_id, quantity) WHERE size_label IS NULL;
            CREATE UNIQUE INDEX package_food_size_named_unique ON package_food_size_choices (size_group_id, lower(btrim(size_label))) WHERE size_label IS NOT NULL;
            CREATE OR REPLACE FUNCTION guard_package_serving_default() RETURNS trigger AS $$
            BEGIN
                IF NEW.default_serving_quantity IS NOT NULL AND EXISTS (SELECT 1 FROM product_families WHERE id = NEW.family_id AND behavior_type = 'main_package') THEN
                    RAISE EXCEPTION 'Main Catering has no per-food serving default' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER package_serving_default_guard BEFORE INSERT OR UPDATE ON package_versions FOR EACH ROW EXECUTE FUNCTION guard_package_serving_default();
            SQL);
    }

    public function down(): void
    {
        if (DB::table('package_versions')->whereNotNull('default_serving_quantity')->exists()
            || DB::table('package_food_size_choices')->whereNotNull('size_label')->exists()) {
            throw new RuntimeException('Preserve serving defaults and named size facts before removing their schema.');
        }
        DB::unprepared('DROP TRIGGER package_serving_default_guard ON package_versions; DROP FUNCTION guard_package_serving_default(); DROP INDEX package_food_size_numeric_unique; DROP INDEX package_food_size_named_unique; ALTER TABLE package_food_size_choices DROP CONSTRAINT package_food_size_quantity_finite;');
        Schema::table('package_food_size_choices', function (Blueprint $table): void {
            $table->dropColumn('size_label');
            $table->unique(['size_group_id', 'quantity']);
        });
        Schema::table('package_versions', function (Blueprint $table): void {
            $table->dropForeign(['default_serving_unit_id']);
            $table->dropColumn(['default_serving_quantity', 'default_serving_unit_id', 'default_serving_size_label']);
        });
    }
};
