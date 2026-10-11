<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('variant_versions')->where('status', 'draft')->select('variant_id')
            ->groupBy('variant_id')->havingRaw('count(*) > 1')->exists()) {
            throw new RuntimeException('Multiple active drafts exist for a variant. Resolve them before migrating.');
        }

        Schema::table('products', function (Blueprint $table) {
            $table->timestampTz('archived_at')->nullable();
            $table->uuid('creation_token')->nullable()->unique();
        });
        Schema::table('product_variants', function (Blueprint $table) {
            $table->timestampTz('archived_at')->nullable();
            $table->foreignId('current_version_id')->nullable();
        });
        Schema::table('variant_versions', function (Blueprint $table) {
            $table->jsonb('published_snapshot')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('abandoned_at')->nullable();
            $table->foreignId('abandoned_by')->nullable()->constrained('users')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE variant_versions DROP CONSTRAINT variant_versions_draft_only');
        DB::statement("ALTER TABLE variant_versions ADD CONSTRAINT variant_versions_status_valid CHECK (status IN ('draft', 'published', 'abandoned'))");
        DB::statement("ALTER TABLE variant_versions ADD CONSTRAINT variant_versions_lifecycle_valid CHECK ((status = 'draft' AND published_snapshot IS NULL AND published_at IS NULL AND abandoned_at IS NULL) OR (status = 'published' AND published_snapshot IS NOT NULL AND published_at IS NOT NULL AND published_by IS NOT NULL AND abandoned_at IS NULL) OR (status = 'abandoned' AND published_snapshot IS NULL AND abandoned_at IS NOT NULL AND abandoned_by IS NOT NULL))");
        DB::statement('CREATE UNIQUE INDEX variant_versions_one_active_draft ON variant_versions (variant_id) WHERE status = \'draft\'');
        DB::statement('ALTER TABLE product_variants ADD CONSTRAINT product_variants_current_version_fk FOREIGN KEY (id, current_version_id) REFERENCES variant_versions (variant_id, id) DEFERRABLE INITIALLY IMMEDIATE');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_archive_valid CHECK (archived_at IS NULL OR active = false)');
        DB::statement('ALTER TABLE product_variants ADD CONSTRAINT product_variants_archive_valid CHECK (archived_at IS NULL OR active = false)');
        DB::statement('CREATE INDEX variant_versions_history_idx ON variant_versions (variant_id, version_number DESC)');

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION guard_published_package() RETURNS trigger AS $$
            DECLARE parent_status text; parent_id bigint;
            BEGIN
                IF TG_TABLE_NAME = 'variant_versions' THEN
                    IF TG_OP = 'DELETE' OR (TG_OP = 'UPDATE' AND OLD.status <> 'draft') THEN
                        RAISE EXCEPTION 'Published or abandoned versions are immutable' USING ERRCODE = '23514';
                    END IF;
                    RETURN NEW;
                END IF;
                IF TG_TABLE_NAME = 'selection_groups' THEN
                    parent_id := CASE WHEN TG_OP = 'DELETE' THEN OLD.version_id ELSE NEW.version_id END;
                    IF TG_OP = 'UPDATE' AND OLD.version_id IS DISTINCT FROM NEW.version_id THEN
                        RAISE EXCEPTION 'Selection Group ownership is immutable' USING ERRCODE = '23514';
                    END IF;
                ELSIF TG_TABLE_NAME = 'group_options' THEN
                    SELECT version_id INTO parent_id FROM selection_groups WHERE id = CASE WHEN TG_OP = 'DELETE' THEN OLD.group_id ELSE NEW.group_id END;
                    IF TG_OP = 'UPDATE' AND OLD.group_id IS DISTINCT FROM NEW.group_id THEN
                        RAISE EXCEPTION 'Group Allowed Option ownership is immutable' USING ERRCODE = '23514';
                    END IF;
                ELSE
                    SELECT version_id INTO parent_id FROM selection_groups WHERE id = CASE WHEN TG_OP = 'DELETE' THEN OLD.group_id ELSE NEW.group_id END;
                    IF TG_OP = 'UPDATE' AND OLD.group_id IS DISTINCT FROM NEW.group_id THEN
                        RAISE EXCEPTION 'Group rule ownership is immutable' USING ERRCODE = '23514';
                    END IF;
                END IF;
                SELECT status INTO parent_status FROM variant_versions WHERE id = parent_id;
                IF parent_status IS DISTINCT FROM 'draft' THEN
                    RAISE EXCEPTION 'Only active drafts may be changed' USING ERRCODE = '23514';
                END IF;
                RETURN CASE WHEN TG_OP = 'DELETE' THEN OLD ELSE NEW END;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER variant_versions_immutable BEFORE UPDATE OR DELETE ON variant_versions FOR EACH ROW EXECUTE FUNCTION guard_published_package();
            CREATE TRIGGER selection_groups_immutable BEFORE INSERT OR UPDATE OR DELETE ON selection_groups FOR EACH ROW EXECUTE FUNCTION guard_published_package();
            CREATE TRIGGER group_options_immutable BEFORE INSERT OR UPDATE OR DELETE ON group_options FOR EACH ROW EXECUTE FUNCTION guard_published_package();
            CREATE TRIGGER group_category_rules_immutable BEFORE INSERT OR UPDATE OR DELETE ON group_category_rules FOR EACH ROW EXECUTE FUNCTION guard_published_package();
            CREATE TRIGGER group_dish_rules_immutable BEFORE INSERT OR UPDATE OR DELETE ON group_dish_rules FOR EACH ROW EXECUTE FUNCTION guard_published_package();
            CREATE OR REPLACE FUNCTION guard_current_package_version() RETURNS trigger AS $$
            DECLARE old_number integer; new_number integer;
            BEGIN
                IF OLD.current_version_id IS NOT NULL AND NEW.current_version_id IS NULL THEN
                    RAISE EXCEPTION 'The current published version cannot be cleared' USING ERRCODE = '23514';
                END IF;
                IF NEW.current_version_id IS NOT NULL AND NOT EXISTS (
                    SELECT 1 FROM variant_versions WHERE id = NEW.current_version_id AND variant_id = NEW.id AND status = 'published'
                ) THEN
                    RAISE EXCEPTION 'Current version must be published and belong to this variant' USING ERRCODE = '23514';
                END IF;
                IF OLD.current_version_id IS NOT NULL AND NEW.current_version_id IS DISTINCT FROM OLD.current_version_id THEN
                    SELECT version_number INTO old_number FROM variant_versions WHERE id = OLD.current_version_id;
                    SELECT version_number INTO new_number FROM variant_versions WHERE id = NEW.current_version_id;
                    IF new_number <= old_number THEN
                        RAISE EXCEPTION 'Current version cannot move backward' USING ERRCODE = '23514';
                    END IF;
                END IF;
                IF OLD.archived_at IS NOT NULL AND NEW.archived_at IS DISTINCT FROM OLD.archived_at THEN
                    RAISE EXCEPTION 'Archived variants cannot be restored' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER product_variants_current_guard BEFORE UPDATE ON product_variants FOR EACH ROW EXECUTE FUNCTION guard_current_package_version();
            CREATE OR REPLACE FUNCTION guard_archived_product() RETURNS trigger AS $$
            BEGIN
                IF OLD.archived_at IS NOT NULL AND NEW.archived_at IS DISTINCT FROM OLD.archived_at THEN
                    RAISE EXCEPTION 'Archived products cannot be restored' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER products_archive_guard BEFORE UPDATE ON products FOR EACH ROW EXECUTE FUNCTION guard_archived_product();
            CREATE OR REPLACE FUNCTION guard_technical_code() RETURNS trigger AS $$
            BEGIN
                IF TG_TABLE_NAME = 'offering_options' THEN
                    IF NEW.option_code IS DISTINCT FROM OLD.option_code THEN
                        RAISE EXCEPTION 'Technical codes are immutable' USING ERRCODE = '23514';
                    END IF;
                ELSIF TG_TABLE_NAME = 'selection_groups' THEN
                    IF NEW.group_code IS DISTINCT FROM OLD.group_code THEN
                        RAISE EXCEPTION 'Technical codes are immutable' USING ERRCODE = '23514';
                    END IF;
                ELSE
                    IF NEW.code IS DISTINCT FROM OLD.code THEN
                        RAISE EXCEPTION 'Technical codes are immutable' USING ERRCODE = '23514';
                    END IF;
                END IF;
                IF TG_TABLE_NAME = 'products' THEN
                    IF NEW.creation_token IS DISTINCT FROM OLD.creation_token THEN
                        RAISE EXCEPTION 'Creation tokens are immutable' USING ERRCODE = '23514';
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
            CREATE TRIGGER variants_code_guard BEFORE UPDATE ON product_variants FOR EACH ROW EXECUTE FUNCTION guard_technical_code();
            CREATE TRIGGER groups_code_guard BEFORE UPDATE ON selection_groups FOR EACH ROW EXECUTE FUNCTION guard_technical_code();
            SQL);
    }

    public function down(): void
    {
        if (DB::table('variant_versions')->where('status', '!=', 'draft')->exists()) {
            throw new RuntimeException('Published or abandoned history cannot be rolled back safely.');
        }
        if (DB::table('products')->whereNotNull('creation_token')->orWhereNotNull('archived_at')->exists()
            || DB::table('product_variants')->whereNotNull('archived_at')->exists()) {
            throw new RuntimeException('Phase 4 creation or archive history cannot be rolled back safely.');
        }
        foreach (['categories_code_guard' => 'dish_categories', 'dishes_code_guard' => 'dishes',
            'offerings_code_guard' => 'dish_offerings', 'options_code_guard' => 'offering_options',
            'products_code_guard' => 'products', 'variants_code_guard' => 'product_variants',
            'groups_code_guard' => 'selection_groups', 'products_archive_guard' => 'products',
            'product_variants_current_guard' => 'product_variants',
            'group_dish_rules_immutable' => 'group_dish_rules', 'group_category_rules_immutable' => 'group_category_rules',
            'group_options_immutable' => 'group_options', 'selection_groups_immutable' => 'selection_groups',
            'variant_versions_immutable' => 'variant_versions'] as $trigger => $table) {
            DB::statement("DROP TRIGGER {$trigger} ON {$table}");
        }
        DB::statement('DROP FUNCTION guard_archived_product()');
        DB::statement('DROP FUNCTION guard_technical_code()');
        DB::statement('DROP FUNCTION guard_current_package_version()');
        DB::statement('DROP FUNCTION guard_published_package()');
        DB::statement('ALTER TABLE product_variants DROP CONSTRAINT product_variants_current_version_fk');
        DB::statement('DROP INDEX variant_versions_one_active_draft');
        DB::statement('DROP INDEX variant_versions_history_idx');
        DB::statement('ALTER TABLE variant_versions DROP CONSTRAINT variant_versions_lifecycle_valid');
        DB::statement('ALTER TABLE variant_versions DROP CONSTRAINT variant_versions_status_valid');
        DB::statement("ALTER TABLE variant_versions ADD CONSTRAINT variant_versions_draft_only CHECK (status = 'draft')");
        DB::statement('ALTER TABLE products DROP CONSTRAINT products_archive_valid');
        DB::statement('ALTER TABLE product_variants DROP CONSTRAINT product_variants_archive_valid');
        Schema::table('variant_versions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('published_by');
            $table->dropConstrainedForeignId('abandoned_by');
            $table->dropColumn(['published_snapshot', 'published_at', 'abandoned_at']);
        });
        Schema::table('product_variants', fn (Blueprint $table) => $table->dropColumn(['archived_at', 'current_version_id']));
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['creation_token']);
            $table->dropColumn(['archived_at', 'creation_token']);
        });
    }
};
