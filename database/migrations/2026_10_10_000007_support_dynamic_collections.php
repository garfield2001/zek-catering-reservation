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
            $table->string('behavior_type')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->index(['behavior_type', 'active']);
        });
        DB::statement(<<<'SQL'
            UPDATE product_families SET behavior_type = CASE code
                WHEN 'main_catering' THEN 'main_package'
                WHEN 'belly_lechon' THEN 'package'
                WHEN 'food_pack' THEN 'packed_meal'
                WHEN 'food_tray' THEN 'direct_sale'
                WHEN 'pica_pica' THEN 'fixed_bundle'
            END
            WHERE behavior_type IS NULL
            SQL);
        if (DB::table('product_families')->whereNull('behavior_type')->exists()) {
            throw new RuntimeException('Some existing collections have no unambiguous behavior mapping. Resolve them before applying Phase 3.5.');
        }
        DB::statement('ALTER TABLE product_families ALTER COLUMN behavior_type SET NOT NULL');
        DB::statement('ALTER TABLE product_families DROP CONSTRAINT product_families_code_valid');
        DB::statement("ALTER TABLE product_families ADD CONSTRAINT product_families_code_valid CHECK (code ~ '^[a-z0-9]+(_[a-z0-9]+)*$')");
        DB::statement(<<<'SQL'
            ALTER TABLE product_families ADD CONSTRAINT product_families_behavior_valid CHECK (
                (is_addon = false AND behavior_type = 'main_package') OR
                (is_addon = true AND behavior_type IN ('package', 'packed_meal', 'direct_sale', 'fixed_bundle'))
            )
            SQL);
        DB::statement('ALTER TABLE product_families ADD CONSTRAINT product_families_archive_valid CHECK (archived_at IS NULL OR active = false)');
        DB::statement('DROP INDEX product_families_name_key_unique');
        DB::statement('CREATE UNIQUE INDEX product_families_name_key_unique ON product_families (lower(btrim(name))) WHERE archived_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX product_families_one_current_primary ON product_families (is_addon) WHERE is_addon = false AND archived_at IS NULL');
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION guard_product_family_identity() RETURNS trigger AS $$
            BEGIN
                IF NEW.code IS DISTINCT FROM OLD.code OR NEW.is_addon IS DISTINCT FROM OLD.is_addon
                   OR NEW.behavior_type IS DISTINCT FROM OLD.behavior_type THEN
                    RAISE EXCEPTION 'Collection code, role and behavior are immutable' USING ERRCODE = '23514';
                END IF;
                IF OLD.archived_at IS NOT NULL AND NEW.archived_at IS DISTINCT FROM OLD.archived_at THEN
                    RAISE EXCEPTION 'Archived collections cannot be restored or re-archived' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            SQL);
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION guard_package_owner_identity() RETURNS trigger AS $$
            BEGIN
                IF TG_TABLE_NAME = 'products' THEN
                    IF NEW.family_id IS DISTINCT FROM OLD.family_id THEN
                        RAISE EXCEPTION 'Package owner relationships are immutable' USING ERRCODE = '23514';
                    END IF;
                ELSIF TG_TABLE_NAME = 'product_variants' THEN
                    IF NEW.product_id IS DISTINCT FROM OLD.product_id THEN
                        RAISE EXCEPTION 'Package owner relationships are immutable' USING ERRCODE = '23514';
                    END IF;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER products_collection_identity_guard BEFORE UPDATE ON products
                FOR EACH ROW EXECUTE FUNCTION guard_package_owner_identity();
            CREATE TRIGGER variants_product_identity_guard BEFORE UPDATE ON product_variants
                FOR EACH ROW EXECUTE FUNCTION guard_package_owner_identity();
            SQL);

        DB::statement('ALTER TABLE selection_groups DROP CONSTRAINT selection_groups_slot_kind_valid');
        DB::statement("ALTER TABLE selection_groups ADD CONSTRAINT selection_groups_slot_kind_valid CHECK (slot_kind IN ('item', 'rice', 'drink', 'dish', 'lechon', 'tray', 'bundle_item'))");
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION check_package_draft_family() RETURNS trigger AS $$
            DECLARE expected_family_id bigint; expected_behavior text; actual_family_id bigint;
                expected_slot text; actual_unit text;
            BEGIN
                IF TG_TABLE_NAME = 'product_variants' THEN
                    SELECT family_id INTO expected_family_id FROM products WHERE id = NEW.product_id;
                    SELECT behavior_type INTO expected_behavior FROM product_families WHERE id = expected_family_id;
                    IF expected_behavior = 'direct_sale' THEN
                        RAISE EXCEPTION 'Direct-sale collections use listings, not package variants' USING ERRCODE = '23514';
                    END IF;
                ELSIF TG_TABLE_NAME = 'variant_versions' THEN
                    SELECT products.family_id INTO expected_family_id FROM product_variants
                        JOIN products ON products.id = product_variants.product_id WHERE product_variants.id = NEW.variant_id;
                    IF NEW.family_id <> expected_family_id THEN
                        RAISE EXCEPTION 'Version collection does not match its product' USING ERRCODE = '23514';
                    END IF;
                    SELECT behavior_type INTO expected_behavior FROM product_families WHERE id = expected_family_id;
                    IF NOT ((expected_behavior = 'main_package' AND NEW.price_basis = 'per_pax' AND NEW.selection_policy = 'uniform')
                        OR (expected_behavior = 'packed_meal' AND NEW.price_basis = 'per_pack')
                        OR (expected_behavior IN ('package', 'fixed_bundle') AND NEW.price_basis = 'per_set' AND NEW.selection_policy = 'uniform')) THEN
                        RAISE EXCEPTION 'Version price basis or selection policy conflicts with its collection behavior' USING ERRCODE = '23514';
                    END IF;
                ELSIF TG_TABLE_NAME = 'selection_groups' THEN
                    SELECT product_families.behavior_type INTO expected_behavior FROM variant_versions
                        JOIN product_families ON product_families.id = variant_versions.family_id WHERE variant_versions.id = NEW.version_id;
                    IF NOT ((expected_behavior IN ('main_package', 'package', 'packed_meal') AND
                            ((NEW.slot_kind = 'item')
                                OR (NEW.slot_kind = 'rice' AND NEW.mode = 'fixed')
                                OR (NEW.slot_kind = 'drink' AND NEW.mode = 'select_one')
                                OR (NEW.slot_kind = 'dish' AND NEW.mode = 'select_many')
                                OR (NEW.slot_kind = 'lechon' AND NEW.mode IN ('fixed', 'select_one'))
                                OR (NEW.slot_kind = 'tray' AND NEW.mode = 'select_many')
                                OR (NEW.slot_kind = 'bundle_item' AND NEW.mode = 'fixed')))
                        OR (expected_behavior = 'fixed_bundle' AND NEW.slot_kind IN ('item', 'bundle_item') AND NEW.mode = 'fixed')) THEN
                        RAISE EXCEPTION 'Selection slot type or mode conflicts with its collection behavior' USING ERRCODE = '23514';
                    END IF;
                    IF (NEW.slot_kind = 'lechon' AND EXISTS (
                        SELECT 1 FROM group_options JOIN offering_options ON offering_options.id = group_options.offering_option_id
                        WHERE group_options.group_id = NEW.id AND offering_options.unit <> 'kg'
                    )) OR (NEW.slot_kind = 'tray' AND EXISTS (
                        SELECT 1 FROM group_options JOIN offering_options ON offering_options.id = group_options.offering_option_id
                        WHERE group_options.group_id = NEW.id AND offering_options.unit <> 'tray'
                    )) THEN
                        RAISE EXCEPTION 'Existing serving options conflict with the new slot unit' USING ERRCODE = '23514';
                    END IF;
                ELSE
                    SELECT variant_versions.family_id, selection_groups.slot_kind INTO expected_family_id, expected_slot FROM selection_groups
                        JOIN variant_versions ON variant_versions.id = selection_groups.version_id WHERE selection_groups.id = NEW.group_id;
                    SELECT dish_offerings.family_id, offering_options.unit INTO actual_family_id, actual_unit FROM offering_options
                        JOIN dish_offerings ON dish_offerings.id = offering_options.offering_id WHERE offering_options.id = NEW.offering_option_id;
                    IF actual_family_id <> expected_family_id THEN
                        RAISE EXCEPTION 'Group option collection does not match its package' USING ERRCODE = '23514';
                    END IF;
                    IF (expected_slot = 'lechon' AND actual_unit <> 'kg') OR (expected_slot = 'tray' AND actual_unit <> 'tray') THEN
                        RAISE EXCEPTION 'Group option serving unit conflicts with its slot' USING ERRCODE = '23514';
                    END IF;
                    SELECT behavior_type INTO expected_behavior FROM product_families WHERE id = expected_family_id;
                    IF expected_behavior = 'fixed_bundle' AND NEW.price_adjustment_minor <> 0 THEN
                        RAISE EXCEPTION 'Fixed-bundle inclusions cannot have premiums' USING ERRCODE = '23514';
                    END IF;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            SQL);
    }

    public function down(): void
    {
        if (DB::table('product_families')->whereNotIn('code', ['main_catering', 'belly_lechon', 'food_pack', 'food_tray', 'pica_pica'])->exists()) {
            throw new RuntimeException('Cannot roll back dynamic collections while administrator-created collections exist. Archive/export and migrate them explicitly.');
        }
        if (DB::table('product_families')->whereNotNull('archived_at')->exists()
            || DB::table('selection_groups')->where('slot_kind', 'item')->exists()) {
            throw new RuntimeException('Cannot roll back archived collections or generic item slots without an explicit data conversion.');
        }
        DB::statement('DROP TRIGGER IF EXISTS products_collection_identity_guard ON products');
        DB::statement('DROP TRIGGER IF EXISTS variants_product_identity_guard ON product_variants');
        DB::statement('DROP FUNCTION IF EXISTS guard_package_owner_identity()');
        DB::statement('DROP INDEX IF EXISTS product_families_one_current_primary');
        DB::statement('DROP INDEX product_families_name_key_unique');
        DB::statement('CREATE UNIQUE INDEX product_families_name_key_unique ON product_families (lower(btrim(name)))');
        DB::statement('ALTER TABLE product_families DROP CONSTRAINT product_families_archive_valid');
        DB::statement('ALTER TABLE product_families DROP CONSTRAINT product_families_behavior_valid');
        DB::statement('ALTER TABLE product_families DROP CONSTRAINT product_families_code_valid');
        DB::statement("ALTER TABLE product_families ADD CONSTRAINT product_families_code_valid CHECK (code IN ('main_catering', 'belly_lechon', 'food_pack', 'food_tray', 'pica_pica'))");
        DB::statement('ALTER TABLE selection_groups DROP CONSTRAINT selection_groups_slot_kind_valid');
        DB::statement("ALTER TABLE selection_groups ADD CONSTRAINT selection_groups_slot_kind_valid CHECK (slot_kind IN ('rice', 'drink', 'dish', 'lechon', 'tray', 'bundle_item'))");
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION check_package_draft_family() RETURNS trigger AS $$
            DECLARE expected_family_id bigint; expected_code text; actual_family_id bigint;
                expected_slot text; actual_unit text;
            BEGIN
                IF TG_TABLE_NAME = 'product_variants' THEN
                    SELECT family_id INTO expected_family_id FROM products WHERE id = NEW.product_id;
                    SELECT code INTO expected_code FROM product_families WHERE id = expected_family_id;
                    IF expected_code = 'food_tray' THEN
                        RAISE EXCEPTION 'Food Tray uses direct listings, not package variants' USING ERRCODE = '23514';
                    END IF;
                ELSIF TG_TABLE_NAME = 'variant_versions' THEN
                    SELECT products.family_id INTO expected_family_id FROM product_variants
                        JOIN products ON products.id = product_variants.product_id WHERE product_variants.id = NEW.variant_id;
                    IF NEW.family_id <> expected_family_id THEN
                        RAISE EXCEPTION 'Version family does not match its product' USING ERRCODE = '23514';
                    END IF;
                    SELECT code INTO expected_code FROM product_families WHERE id = expected_family_id;
                    IF NOT ((expected_code = 'main_catering' AND NEW.price_basis = 'per_pax' AND NEW.selection_policy = 'uniform')
                        OR (expected_code = 'food_pack' AND NEW.price_basis = 'per_pack')
                        OR (expected_code IN ('belly_lechon', 'pica_pica') AND NEW.price_basis = 'per_set' AND NEW.selection_policy = 'uniform')) THEN
                        RAISE EXCEPTION 'Version price basis or selection policy conflicts with its family' USING ERRCODE = '23514';
                    END IF;
                ELSIF TG_TABLE_NAME = 'selection_groups' THEN
                    SELECT product_families.code INTO expected_code FROM variant_versions
                        JOIN product_families ON product_families.id = variant_versions.family_id WHERE variant_versions.id = NEW.version_id;
                    IF NOT ((expected_code IN ('main_catering', 'food_pack') AND
                            ((NEW.slot_kind = 'rice' AND NEW.mode = 'fixed') OR (NEW.slot_kind = 'drink' AND NEW.mode = 'select_one' AND NEW.minimum_count = 1) OR (NEW.slot_kind = 'dish' AND NEW.mode = 'select_many')))
                        OR (expected_code = 'belly_lechon' AND ((NEW.slot_kind = 'lechon' AND NEW.mode IN ('fixed', 'select_one')) OR (NEW.slot_kind = 'tray' AND NEW.mode = 'select_many')))
                        OR (expected_code = 'pica_pica' AND NEW.slot_kind = 'bundle_item' AND NEW.mode = 'fixed')) THEN
                        RAISE EXCEPTION 'Selection slot type or mode conflicts with its family' USING ERRCODE = '23514';
                    END IF;
                ELSE
                    SELECT variant_versions.family_id, selection_groups.slot_kind INTO expected_family_id, expected_slot FROM selection_groups
                        JOIN variant_versions ON variant_versions.id = selection_groups.version_id WHERE selection_groups.id = NEW.group_id;
                    SELECT dish_offerings.family_id, offering_options.unit INTO actual_family_id, actual_unit FROM offering_options
                        JOIN dish_offerings ON dish_offerings.id = offering_options.offering_id WHERE offering_options.id = NEW.offering_option_id;
                    IF actual_family_id <> expected_family_id THEN
                        RAISE EXCEPTION 'Group option family does not match its package' USING ERRCODE = '23514';
                    END IF;
                    IF (expected_slot = 'lechon' AND actual_unit <> 'kg') OR (expected_slot = 'tray' AND actual_unit <> 'tray') THEN
                        RAISE EXCEPTION 'Group option serving unit conflicts with its slot' USING ERRCODE = '23514';
                    END IF;
                    SELECT code INTO expected_code FROM product_families WHERE id = expected_family_id;
                    IF expected_code = 'pica_pica' AND NEW.price_adjustment_minor <> 0 THEN
                        RAISE EXCEPTION 'Pica-Pica fixed inclusions cannot have premiums' USING ERRCODE = '23514';
                    END IF;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            SQL);
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION guard_product_family_identity() RETURNS trigger AS $$
            BEGIN
                IF NEW.code IS DISTINCT FROM OLD.code OR NEW.is_addon IS DISTINCT FROM OLD.is_addon THEN
                    RAISE EXCEPTION 'Product family code and primary/add-on identity are immutable' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            SQL);
        Schema::table('product_families', function (Blueprint $table) {
            $table->dropIndex(['behavior_type', 'active']);
            $table->dropColumn(['behavior_type', 'archived_at']);
        });
    }
};
