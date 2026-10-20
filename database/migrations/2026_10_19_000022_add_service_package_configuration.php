<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('service_format_owned')->default(false);
        });
        Schema::table('product_families', function (Blueprint $table): void {
            // Null preserves legacy services; never infer format from a name or old price basis.
            $table->string('package_format', 30)->nullable();
            $table->integer('default_presentation_quantity')->nullable();
            $table->foreignId('default_presentation_unit_id')->nullable()->constrained('measurement_units')->restrictOnDelete();
            $table->index('default_presentation_unit_id');
        });
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION guard_package_format_ownership() RETURNS trigger AS $$
            BEGIN
                IF NEW.service_format_owned IS DISTINCT FROM OLD.service_format_owned THEN
                    RAISE EXCEPTION 'Package format ownership is immutable' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER package_format_ownership_guard BEFORE UPDATE ON products
                FOR EACH ROW EXECUTE FUNCTION guard_package_format_ownership();
            CREATE OR REPLACE FUNCTION guard_service_owned_package_presentation() RETURNS trigger AS $$
            BEGIN
                IF EXISTS (SELECT 1 FROM products WHERE id = NEW.package_product_id AND service_format_owned) THEN
                    IF NEW.package_format IS NULL OR NOT EXISTS (
                        SELECT 1 FROM product_families WHERE id = NEW.family_id AND package_format = NEW.package_format
                    ) THEN
                        RAISE EXCEPTION 'Package format must match its configured service' USING ERRCODE = '23514';
                    END IF;
                    IF TG_OP = 'UPDATE' AND (NEW.presentation_quantity IS DISTINCT FROM OLD.presentation_quantity OR NEW.presentation_unit_id IS DISTINCT FROM OLD.presentation_unit_id) THEN
                        RAISE EXCEPTION 'Inherited presentation is retained by the package version' USING ERRCODE = '23514';
                    END IF;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER service_owned_package_presentation_guard BEFORE INSERT OR UPDATE ON package_versions
                FOR EACH ROW EXECUTE FUNCTION guard_service_owned_package_presentation();
            ALTER TABLE product_families ADD CONSTRAINT service_package_configuration_valid CHECK (
                (package_format IS NULL AND default_presentation_quantity IS NULL AND default_presentation_unit_id IS NULL)
                OR (package_format IS NOT NULL AND package_format IN ('separate_servings', 'combined_contents')
                    AND is_addon AND behavior_type IN ('package', 'packed_meal', 'fixed_bundle')
                    AND default_presentation_quantity IS NOT NULL AND default_presentation_quantity > 0
                    AND default_presentation_unit_id IS NOT NULL
                    AND (package_format <> 'combined_contents' OR default_presentation_quantity = 1))
            );
            CREATE OR REPLACE FUNCTION guard_established_service_package_format() RETURNS trigger AS $$
            BEGIN
                IF OLD.package_format IS NOT NULL AND NEW.package_format IS DISTINCT FROM OLD.package_format
                    AND EXISTS (SELECT 1 FROM products WHERE family_id = OLD.id) THEN
                    RAISE EXCEPTION 'An established service format cannot change; create a separate service' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER established_service_package_format_guard BEFORE UPDATE ON product_families
                FOR EACH ROW EXECUTE FUNCTION guard_established_service_package_format();
            SQL);
    }

    public function down(): void
    {
        if (DB::table('product_families')->whereNotNull('package_format')->exists()) {
            throw new RuntimeException('Preserve configured service package settings before rollback.');
        }
        DB::unprepared('DROP TRIGGER established_service_package_format_guard ON product_families; DROP FUNCTION guard_established_service_package_format(); ALTER TABLE product_families DROP CONSTRAINT service_package_configuration_valid;');
        DB::unprepared('DROP TRIGGER service_owned_package_presentation_guard ON package_versions; DROP FUNCTION guard_service_owned_package_presentation();');
        DB::unprepared('DROP TRIGGER package_format_ownership_guard ON products; DROP FUNCTION guard_package_format_ownership();');
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('service_format_owned'));
        Schema::table('product_families', function (Blueprint $table): void {
            $table->dropForeign(['default_presentation_unit_id']);
            $table->dropColumn(['package_format', 'default_presentation_quantity', 'default_presentation_unit_id']);
        });
    }
};
