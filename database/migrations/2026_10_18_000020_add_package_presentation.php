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
            $table->string('package_format', 30)->nullable();
            $table->integer('presentation_quantity')->nullable();
            $table->foreignId('presentation_unit_id')->nullable()->constrained('measurement_units')->restrictOnDelete();
            $table->index('presentation_unit_id');
        });
        Schema::table('package_version_food_items', function (Blueprint $table): void {
            $table->integer('presentation_quantity')->nullable();
            $table->foreignId('presentation_unit_id')->nullable()->constrained('measurement_units')->restrictOnDelete();
            $table->index('presentation_unit_id');
        });
        DB::unprepared(<<<'SQL'
            ALTER TABLE package_versions ADD CONSTRAINT package_format_valid CHECK (
                package_format IS NULL OR package_format IN ('separate_servings', 'combined_contents')
            );
            ALTER TABLE package_versions ADD CONSTRAINT package_presentation_valid CHECK (
                (presentation_quantity IS NULL AND presentation_unit_id IS NULL)
                OR (presentation_quantity IS NOT NULL AND presentation_quantity > 0 AND presentation_unit_id IS NOT NULL AND package_format IS NOT NULL)
            );
            ALTER TABLE package_versions ADD CONSTRAINT package_combined_unit_valid CHECK (
                package_format IS DISTINCT FROM 'combined_contents' OR (presentation_quantity IS NOT NULL AND presentation_quantity = 1 AND presentation_unit_id IS NOT NULL)
            );
            ALTER TABLE package_versions ADD CONSTRAINT package_format_uniform CHECK (package_format IS NULL OR selection_policy = 'uniform');
            ALTER TABLE package_version_food_items ADD CONSTRAINT food_presentation_valid CHECK (
                (presentation_quantity IS NULL AND presentation_unit_id IS NULL)
                OR (presentation_quantity IS NOT NULL AND presentation_quantity > 0 AND presentation_unit_id IS NOT NULL)
            );
            CREATE OR REPLACE FUNCTION guard_package_presentation() RETURNS trigger AS $$
            BEGIN
                IF NEW.package_format IS NOT NULL AND EXISTS (SELECT 1 FROM product_families WHERE id = NEW.family_id AND behavior_type IN ('main_package', 'direct_sale')) THEN
                    RAISE EXCEPTION 'Package Format is only for add-on packages' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER package_presentation_guard BEFORE INSERT OR UPDATE ON package_versions FOR EACH ROW EXECUTE FUNCTION guard_package_presentation();
            CREATE OR REPLACE FUNCTION check_food_presentation() RETURNS trigger AS $$
            DECLARE version_id bigint;
            BEGIN
                IF TG_TABLE_NAME = 'package_versions' THEN version_id := NEW.id;
                ELSE version_id := NEW.package_version_id;
                END IF;
                IF EXISTS (SELECT 1 FROM package_version_food_items f JOIN package_versions v ON v.id = f.package_version_id
                    WHERE v.id = version_id AND f.presentation_unit_id IS NOT NULL AND v.package_format IS DISTINCT FROM 'separate_servings') THEN
                    RAISE EXCEPTION 'Food presentation overrides require separate servings' USING ERRCODE = '23514';
                END IF;
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;
            CREATE CONSTRAINT TRIGGER food_presentation_guard AFTER INSERT OR UPDATE ON package_version_food_items DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION check_food_presentation();
            CREATE CONSTRAINT TRIGGER package_food_presentation_guard AFTER INSERT OR UPDATE ON package_versions DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION check_food_presentation();
            SQL);
    }

    public function down(): void
    {
        if (DB::table('package_versions')->whereNotNull('package_format')->exists()) {
            throw new RuntimeException('Preserve versioned package presentation facts before rolling back.');
        }
        DB::unprepared('DROP TRIGGER package_food_presentation_guard ON package_versions; DROP TRIGGER food_presentation_guard ON package_version_food_items; DROP FUNCTION check_food_presentation(); DROP TRIGGER package_presentation_guard ON package_versions; DROP FUNCTION guard_package_presentation();');
        foreach (['package_version_food_items', 'package_versions'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropForeign(['presentation_unit_id']);
                $table->dropColumn(['presentation_quantity', 'presentation_unit_id']);
            });
        }
        Schema::table('package_versions', fn (Blueprint $table) => $table->dropColumn('package_format'));
    }
};
