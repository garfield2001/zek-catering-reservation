<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION guard_package_owner_identity() RETURNS trigger AS $$
            BEGIN
                IF NEW.family_id IS DISTINCT FROM OLD.family_id THEN
                    RAISE EXCEPTION 'Package collection ownership is immutable' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER products_collection_identity_guard BEFORE UPDATE ON products
                FOR EACH ROW EXECUTE FUNCTION guard_package_owner_identity();

            CREATE OR REPLACE FUNCTION guard_product_family_identity() RETURNS trigger AS $$
            BEGIN
                IF NEW.code IS DISTINCT FROM OLD.code OR NEW.is_addon IS DISTINCT FROM OLD.is_addon
                   OR NEW.behavior_type IS DISTINCT FROM OLD.behavior_type THEN
                    RAISE EXCEPTION 'Collection code, role and behavior are immutable' USING ERRCODE = '23514';
                END IF;
                IF OLD.archived_at IS NOT NULL AND NEW.archived_at IS DISTINCT FROM OLD.archived_at THEN
                    RAISE EXCEPTION 'Archived collections cannot be restored' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER product_family_identity_guard BEFORE UPDATE ON product_families
                FOR EACH ROW EXECUTE FUNCTION guard_product_family_identity();

            CREATE OR REPLACE FUNCTION guard_archived_product() RETURNS trigger AS $$
            BEGIN
                IF OLD.archived_at IS NOT NULL AND NEW.archived_at IS DISTINCT FROM OLD.archived_at THEN
                    RAISE EXCEPTION 'Archived packages cannot be restored' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER products_archive_guard BEFORE UPDATE ON products
                FOR EACH ROW EXECUTE FUNCTION guard_archived_product();

            CREATE OR REPLACE FUNCTION guard_published_package() RETURNS trigger AS $$
            DECLARE parent_status text; parent_id bigint;
            BEGIN
                IF TG_TABLE_NAME = 'package_versions' THEN
                    IF TG_OP = 'UPDATE' AND (NEW.package_product_id IS DISTINCT FROM OLD.package_product_id OR NEW.family_id IS DISTINCT FROM OLD.family_id OR NEW.version_number IS DISTINCT FROM OLD.version_number OR NEW.cloned_from_id IS DISTINCT FROM OLD.cloned_from_id) THEN
                        RAISE EXCEPTION 'Package Version ownership and identity are immutable' USING ERRCODE = '23514';
                    END IF;
                    IF TG_OP = 'DELETE' OR (TG_OP = 'UPDATE' AND OLD.status <> 'draft') THEN
                        RAISE EXCEPTION 'Published or abandoned package versions are immutable' USING ERRCODE = '23514';
                    END IF;
                    RETURN NEW;
                END IF;
                IF TG_TABLE_NAME = 'selection_groups' THEN
                    IF TG_OP = 'UPDATE' AND NEW.package_version_id IS DISTINCT FROM OLD.package_version_id THEN
                        RAISE EXCEPTION 'Configuration ownership is immutable' USING ERRCODE = '23514';
                    END IF;
                    parent_id := CASE WHEN TG_OP = 'DELETE' THEN OLD.package_version_id ELSE NEW.package_version_id END;
                ELSIF TG_TABLE_NAME IN ('package_version_food_items','package_version_required_categories') THEN
                    IF TG_OP = 'UPDATE' AND NEW.package_version_id IS DISTINCT FROM OLD.package_version_id THEN
                        RAISE EXCEPTION 'Menu ownership is immutable' USING ERRCODE = '23514';
                    END IF;
                    parent_id := CASE WHEN TG_OP = 'DELETE' THEN OLD.package_version_id ELSE NEW.package_version_id END;
                ELSE
                    IF TG_OP = 'UPDATE' AND NEW.group_id IS DISTINCT FROM OLD.group_id THEN
                        RAISE EXCEPTION 'Inclusion ownership is immutable' USING ERRCODE = '23514';
                    END IF;
                    SELECT package_version_id INTO parent_id FROM selection_groups WHERE id = CASE WHEN TG_OP = 'DELETE' THEN OLD.group_id ELSE NEW.group_id END;
                END IF;
                SELECT status INTO parent_status FROM package_versions WHERE id = parent_id FOR UPDATE;
                IF parent_status IS DISTINCT FROM 'draft' THEN
                    RAISE EXCEPTION 'Only active package drafts may be changed' USING ERRCODE = '23514';
                END IF;
                RETURN CASE WHEN TG_OP = 'DELETE' THEN OLD ELSE NEW END;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER package_versions_immutable BEFORE UPDATE OR DELETE ON package_versions FOR EACH ROW EXECUTE FUNCTION guard_published_package();
            CREATE TRIGGER selection_groups_immutable BEFORE INSERT OR UPDATE OR DELETE ON selection_groups FOR EACH ROW EXECUTE FUNCTION guard_published_package();
            CREATE TRIGGER group_options_immutable BEFORE INSERT OR UPDATE OR DELETE ON group_options FOR EACH ROW EXECUTE FUNCTION guard_published_package();
            CREATE TRIGGER group_category_rules_immutable BEFORE INSERT OR UPDATE OR DELETE ON group_category_rules FOR EACH ROW EXECUTE FUNCTION guard_published_package();
            CREATE TRIGGER group_dish_rules_immutable BEFORE INSERT OR UPDATE OR DELETE ON group_dish_rules FOR EACH ROW EXECUTE FUNCTION guard_published_package();
            CREATE TRIGGER package_version_food_items_immutable BEFORE INSERT OR UPDATE OR DELETE ON package_version_food_items FOR EACH ROW EXECUTE FUNCTION guard_published_package();
            CREATE TRIGGER package_version_required_categories_immutable BEFORE INSERT OR UPDATE OR DELETE ON package_version_required_categories FOR EACH ROW EXECUTE FUNCTION guard_published_package();

            CREATE OR REPLACE FUNCTION guard_current_package_version() RETURNS trigger AS $$
            DECLARE old_number integer; new_number integer;
            BEGIN
                IF OLD.current_version_id IS NOT NULL AND NEW.current_version_id IS NULL THEN
                    RAISE EXCEPTION 'The current published package version cannot be cleared' USING ERRCODE = '23514';
                END IF;
                IF NEW.current_version_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM package_versions WHERE id = NEW.current_version_id AND package_product_id = NEW.id AND status = 'published') THEN
                    RAISE EXCEPTION 'Current package version must be published and belong to this package' USING ERRCODE = '23514';
                END IF;
                IF OLD.current_version_id IS NOT NULL AND NEW.current_version_id IS DISTINCT FROM OLD.current_version_id THEN
                    SELECT version_number INTO old_number FROM package_versions WHERE id = OLD.current_version_id;
                    SELECT version_number INTO new_number FROM package_versions WHERE id = NEW.current_version_id;
                    IF new_number <= old_number THEN RAISE EXCEPTION 'Current package version cannot move backward' USING ERRCODE = '23514'; END IF;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER products_current_package_guard BEFORE UPDATE ON products FOR EACH ROW EXECUTE FUNCTION guard_current_package_version();

            CREATE OR REPLACE FUNCTION guard_technical_code() RETURNS trigger AS $$
            BEGIN
                IF TG_TABLE_NAME = 'offering_options' THEN
                    IF NEW.option_code IS DISTINCT FROM OLD.option_code THEN RAISE EXCEPTION 'Technical codes are immutable' USING ERRCODE = '23514'; END IF;
                ELSIF TG_TABLE_NAME = 'selection_groups' THEN
                    IF NEW.group_code IS DISTINCT FROM OLD.group_code THEN RAISE EXCEPTION 'Technical codes are immutable' USING ERRCODE = '23514'; END IF;
                ELSE
                    IF NEW.code IS DISTINCT FROM OLD.code THEN RAISE EXCEPTION 'Technical codes are immutable' USING ERRCODE = '23514'; END IF;
                    IF TG_TABLE_NAME = 'products' THEN
                        IF NEW.creation_token IS DISTINCT FROM OLD.creation_token THEN RAISE EXCEPTION 'Creation tokens are immutable' USING ERRCODE = '23514'; END IF;
                    END IF;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER categories_code_guard BEFORE UPDATE ON dish_categories FOR EACH ROW EXECUTE FUNCTION guard_technical_code();
            CREATE TRIGGER dishes_code_guard BEFORE UPDATE ON dishes FOR EACH ROW EXECUTE FUNCTION guard_technical_code();
            CREATE TRIGGER offerings_code_guard BEFORE UPDATE ON dish_offerings FOR EACH ROW EXECUTE FUNCTION guard_technical_code();
            CREATE TRIGGER options_code_guard BEFORE UPDATE ON offering_options FOR EACH ROW EXECUTE FUNCTION guard_technical_code();
            CREATE TRIGGER products_code_guard BEFORE UPDATE ON products FOR EACH ROW EXECUTE FUNCTION guard_technical_code();
            CREATE TRIGGER groups_code_guard BEFORE UPDATE ON selection_groups FOR EACH ROW EXECUTE FUNCTION guard_technical_code();
            SQL);
    }

    public function down(): void
    {
        foreach (['groups_code_guard' => 'selection_groups', 'products_code_guard' => 'products', 'options_code_guard' => 'offering_options', 'offerings_code_guard' => 'dish_offerings', 'dishes_code_guard' => 'dishes', 'categories_code_guard' => 'dish_categories', 'product_family_identity_guard' => 'product_families', 'package_version_required_categories_immutable' => 'package_version_required_categories', 'package_version_food_items_immutable' => 'package_version_food_items', 'group_dish_rules_immutable' => 'group_dish_rules', 'group_category_rules_immutable' => 'group_category_rules', 'group_options_immutable' => 'group_options', 'selection_groups_immutable' => 'selection_groups', 'package_versions_immutable' => 'package_versions', 'products_current_package_guard' => 'products', 'products_archive_guard' => 'products', 'products_collection_identity_guard' => 'products'] as $trigger => $table) {
            DB::statement("DROP TRIGGER IF EXISTS {$trigger} ON {$table}");
        }
        foreach (['guard_technical_code', 'guard_current_package_version', 'guard_published_package', 'guard_archived_product', 'guard_package_owner_identity', 'guard_product_family_identity'] as $function) {
            DB::statement("DROP FUNCTION IF EXISTS {$function}()");
        }
    }
};
