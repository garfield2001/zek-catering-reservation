<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION check_package_configuration() RETURNS trigger AS $$
            DECLARE expected_family_id bigint; expected_behavior text; actual_family_id bigint; expected_slot text; actual_unit text;
            BEGIN
                IF TG_TABLE_NAME = 'products' THEN
                    SELECT behavior_type INTO expected_behavior FROM product_families WHERE id = NEW.family_id;
                    IF expected_behavior = 'direct_sale' THEN RAISE EXCEPTION 'Direct-sale collections use listings, not package products' USING ERRCODE = '23514'; END IF;
                ELSIF TG_TABLE_NAME = 'package_versions' THEN
                    IF NEW.cloned_from_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM package_versions WHERE id = NEW.cloned_from_id AND package_product_id = NEW.package_product_id AND status = 'published') THEN
                        RAISE EXCEPTION 'Revisions must originate from a published version of the same package' USING ERRCODE = '23514';
                    END IF;
                    SELECT family_id INTO expected_family_id FROM products WHERE id = NEW.package_product_id;
                    IF NEW.family_id <> expected_family_id THEN RAISE EXCEPTION 'Package Version collection does not match its Package Product' USING ERRCODE = '23514'; END IF;
                    SELECT behavior_type INTO expected_behavior FROM product_families WHERE id = expected_family_id;
                    IF NOT ((expected_behavior = 'main_package' AND NEW.price_basis = 'per_pax' AND NEW.selection_policy = 'uniform') OR (expected_behavior = 'packed_meal' AND NEW.price_basis = 'per_pack') OR (expected_behavior IN ('package','fixed_bundle') AND NEW.price_basis = 'per_set' AND NEW.selection_policy = 'uniform')) THEN RAISE EXCEPTION 'Package Version pricing or selection policy conflicts with its Collection behavior' USING ERRCODE = '23514'; END IF;
                ELSIF TG_TABLE_NAME = 'selection_groups' THEN
                    SELECT behavior_type INTO expected_behavior FROM package_versions JOIN product_families ON product_families.id = package_versions.family_id WHERE package_versions.id = NEW.package_version_id;
                    IF EXISTS (SELECT 1 FROM package_version_food_items WHERE package_version_id = NEW.package_version_id) THEN RAISE EXCEPTION 'Use one menu configuration model per package' USING ERRCODE = '23514'; END IF;
                    IF expected_behavior = 'main_package' THEN RAISE EXCEPTION 'Catering packages use menu configuration, not selection groups' USING ERRCODE = '23514'; END IF;
                    IF expected_behavior = 'fixed_bundle' AND (NEW.mode <> 'fixed' OR NEW.minimum_count <> 1 OR NEW.maximum_count <> 1) THEN RAISE EXCEPTION 'Fixed bundles must contain fixed single-item inclusions' USING ERRCODE = '23514'; END IF;
                    IF EXISTS (SELECT 1 FROM group_options JOIN offering_options ON offering_options.id = group_options.offering_option_id WHERE group_options.group_id = NEW.id AND ((NEW.slot_kind = 'lechon' AND offering_options.unit <> 'kg') OR (NEW.slot_kind = 'tray' AND offering_options.unit <> 'tray'))) THEN
                        RAISE EXCEPTION 'Existing Serving Options conflict with the inclusion unit' USING ERRCODE = '23514';
                    END IF;
                ELSIF TG_TABLE_NAME IN ('package_version_food_items','package_version_required_categories') THEN
                    SELECT behavior_type INTO expected_behavior FROM package_versions JOIN product_families ON product_families.id = package_versions.family_id WHERE package_versions.id = NEW.package_version_id;
                    IF EXISTS (SELECT 1 FROM selection_groups WHERE package_version_id = NEW.package_version_id) THEN RAISE EXCEPTION 'Use one menu configuration model per package' USING ERRCODE = '23514'; END IF;
                    IF expected_behavior = 'direct_sale' THEN RAISE EXCEPTION 'Direct sale has no package menu' USING ERRCODE = '23514'; END IF;
                    IF TG_TABLE_NAME = 'package_version_food_items' THEN
                        SELECT family_id INTO expected_family_id FROM package_versions WHERE id = NEW.package_version_id;
                        IF NOT EXISTS (SELECT 1 FROM dish_family_eligibilities WHERE dish_id = NEW.dish_id AND family_id = expected_family_id) THEN RAISE EXCEPTION 'Food is not eligible for this service' USING ERRCODE = '23514'; END IF;
                        IF expected_behavior = 'main_package' AND (NEW.always_include OR NEW.included_quantity IS NOT NULL OR NEW.included_unit IS NOT NULL OR NEW.price_adjustment_minor <> 0) THEN RAISE EXCEPTION 'Catering foods have no quantities or premiums' USING ERRCODE = '23514'; END IF;
                        IF expected_behavior = 'fixed_bundle' AND (NOT NEW.is_fixed OR NEW.price_adjustment_minor <> 0) THEN RAISE EXCEPTION 'Bundles have fixed foods without premiums' USING ERRCODE = '23514'; END IF;
                    END IF;
                ELSE
                    SELECT package_versions.family_id, selection_groups.slot_kind INTO expected_family_id, expected_slot FROM selection_groups JOIN package_versions ON package_versions.id = selection_groups.package_version_id WHERE selection_groups.id = NEW.group_id;
                    SELECT dish_offerings.family_id, offering_options.unit INTO actual_family_id, actual_unit FROM offering_options JOIN dish_offerings ON dish_offerings.id = offering_options.offering_id WHERE offering_options.id = NEW.offering_option_id;
                    IF actual_family_id <> expected_family_id THEN RAISE EXCEPTION 'Serving Option collection does not match its Package Version' USING ERRCODE = '23514'; END IF;
                    IF (expected_slot = 'lechon' AND actual_unit <> 'kg') OR (expected_slot = 'tray' AND actual_unit <> 'tray') THEN RAISE EXCEPTION 'Serving Option unit conflicts with its selection group' USING ERRCODE = '23514'; END IF;
                    SELECT behavior_type INTO expected_behavior FROM product_families WHERE id = expected_family_id;
                    IF expected_behavior IN ('main_package','fixed_bundle') AND NEW.price_adjustment_minor <> 0 THEN RAISE EXCEPTION 'This Collection does not allow Food Item surcharges' USING ERRCODE = '23514'; END IF;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER package_product_behavior_guard BEFORE INSERT OR UPDATE ON products FOR EACH ROW EXECUTE FUNCTION check_package_configuration();
            CREATE TRIGGER package_version_configuration_guard BEFORE INSERT OR UPDATE ON package_versions FOR EACH ROW EXECUTE FUNCTION check_package_configuration();
            CREATE TRIGGER package_group_configuration_guard BEFORE INSERT OR UPDATE ON selection_groups FOR EACH ROW EXECUTE FUNCTION check_package_configuration();
            CREATE TRIGGER package_option_configuration_guard BEFORE INSERT OR UPDATE ON group_options FOR EACH ROW EXECUTE FUNCTION check_package_configuration();
            CREATE TRIGGER package_menu_behavior_guard BEFORE INSERT OR UPDATE ON package_version_food_items FOR EACH ROW EXECUTE FUNCTION check_package_configuration();
            CREATE TRIGGER package_category_behavior_guard BEFORE INSERT OR UPDATE ON package_version_required_categories FOR EACH ROW EXECUTE FUNCTION check_package_configuration();
            SQL);
    }

    public function down(): void
    {
        foreach (['package_menu_behavior_guard' => 'package_version_food_items', 'package_category_behavior_guard' => 'package_version_required_categories', 'package_product_behavior_guard' => 'products', 'package_version_configuration_guard' => 'package_versions', 'package_group_configuration_guard' => 'selection_groups', 'package_option_configuration_guard' => 'group_options'] as $trigger => $table) {
            DB::statement("DROP TRIGGER IF EXISTS {$trigger} ON {$table}");
        }
        DB::statement('DROP FUNCTION IF EXISTS check_package_configuration()');
    }
};
