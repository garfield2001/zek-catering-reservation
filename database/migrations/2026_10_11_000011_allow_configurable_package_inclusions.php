<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Selection groups are business-configured inclusions. Their slot kind
        // must not force rice, drinks or dishes to be mandatory, so all normal
        // food slots accept the three supported selection modes.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION check_package_draft_family() RETURNS trigger AS $$
            DECLARE expected_family_id bigint; expected_code text; expected_behavior text; actual_family_id bigint;
                expected_slot text; actual_unit text;
            BEGIN
                IF TG_TABLE_NAME = 'product_variants' THEN
                    SELECT family_id INTO expected_family_id FROM products WHERE id = NEW.product_id;
                    SELECT code, behavior_type INTO expected_code, expected_behavior FROM product_families WHERE id = expected_family_id;
                    IF expected_code = 'food_tray' OR expected_behavior = 'direct_sale' THEN
                        RAISE EXCEPTION 'Food Tray uses direct listings, not package variants' USING ERRCODE = '23514';
                    END IF;
                ELSIF TG_TABLE_NAME = 'variant_versions' THEN
                    SELECT products.family_id INTO expected_family_id FROM product_variants
                        JOIN products ON products.id = product_variants.product_id WHERE product_variants.id = NEW.variant_id;
                    IF NEW.family_id <> expected_family_id THEN
                        RAISE EXCEPTION 'Version family does not match its product' USING ERRCODE = '23514';
                    END IF;
                    SELECT code INTO expected_code FROM product_families WHERE id = expected_family_id;
                    IF expected_code IN ('main_catering', 'food_pack', 'belly_lechon', 'pica_pica')
                        AND NOT ((expected_code = 'main_catering' AND NEW.price_basis = 'per_pax' AND NEW.selection_policy = 'uniform')
                        OR (expected_code = 'food_pack' AND NEW.price_basis = 'per_pack')
                        OR (expected_code IN ('belly_lechon', 'pica_pica') AND NEW.price_basis = 'per_set' AND NEW.selection_policy = 'uniform')) THEN
                        RAISE EXCEPTION 'Version price basis or selection policy conflicts with its family' USING ERRCODE = '23514';
                    END IF;
                ELSIF TG_TABLE_NAME = 'selection_groups' THEN
                    SELECT product_families.code INTO expected_code FROM variant_versions
                        JOIN product_families ON product_families.id = variant_versions.family_id WHERE variant_versions.id = NEW.version_id;
                    IF (expected_code IN ('main_catering', 'food_pack') AND NEW.slot_kind NOT IN ('item', 'rice', 'drink', 'dish'))
                        OR (expected_code = 'belly_lechon' AND NEW.slot_kind NOT IN ('item', 'lechon', 'tray', 'rice', 'drink', 'dish'))
                        OR (expected_code = 'pica_pica' AND (NEW.slot_kind NOT IN ('item', 'bundle_item') OR NEW.mode <> 'fixed')) THEN
                        RAISE EXCEPTION 'Selection slot type conflicts with its collection' USING ERRCODE = '23514';
                    END IF;
                    IF expected_code IN ('main_catering', 'food_pack', 'belly_lechon') AND NEW.slot_kind <> 'item'
                        AND NEW.mode = 'fixed' AND (NEW.minimum_count <> 1 OR NEW.maximum_count <> 1) THEN
                        RAISE EXCEPTION 'Fixed inclusions must select exactly one serving option' USING ERRCODE = '23514';
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
    }

    public function down(): void
    {
        throw new RuntimeException('The configurable inclusion trigger is a compatibility correction and is not safely reversible after package data may use it.');
    }
};
