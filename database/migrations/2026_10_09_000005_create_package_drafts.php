<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained('product_families')->restrictOnDelete();
            $table->string('code', 80);
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestampsTz();
            $table->unique(['family_id', 'code']);
        });
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('code', 80);
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestampsTz();
            $table->unique(['product_id', 'code']);
        });
        Schema::create('variant_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->foreignId('family_id')->constrained('product_families')->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('status')->default('draft');
            $table->unsignedInteger('draft_revision')->default(1);
            $table->foreignId('cloned_from_id')->nullable()->constrained('variant_versions')->restrictOnDelete();
            $table->bigInteger('base_price_minor');
            $table->char('currency', 3)->default('PHP');
            $table->string('price_basis');
            $table->string('selection_policy')->default('uniform');
            $table->unsignedInteger('minimum_override')->nullable();
            $table->unsignedInteger('step_override')->nullable();
            $table->unsignedInteger('maximum_override')->nullable();
            $table->boolean('maximum_override_enabled')->default(false);
            $table->unsignedInteger('minimum_quantity');
            $table->unsignedInteger('quantity_step');
            $table->unsignedInteger('maximum_quantity')->nullable();
            $table->unsignedInteger('subgroup_minimum')->nullable();
            $table->unsignedInteger('subgroup_step')->nullable();
            $table->unsignedInteger('subgroup_maximum')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
            $table->unique(['variant_id', 'version_number']);
            $table->unique(['variant_id', 'id']);
            $table->index(['family_id', 'status']);
        });
        Schema::create('selection_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('version_id')->constrained('variant_versions')->restrictOnDelete();
            $table->string('group_code', 80);
            $table->string('name');
            $table->string('mode');
            $table->string('slot_kind');
            $table->unsignedInteger('minimum_count');
            $table->unsignedInteger('maximum_count');
            $table->boolean('unique_dishes')->default(true);
            $table->string('category_mode')->default('free');
            $table->decimal('inclusion_count', 12, 3)->default(1);
            $table->integer('sort_order')->default(0);
            $table->timestampsTz();
            $table->unique(['version_id', 'group_code']);
            $table->index(['version_id', 'sort_order']);
        });
        Schema::create('group_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('selection_groups')->restrictOnDelete();
            $table->foreignId('offering_option_id')->constrained('offering_options')->restrictOnDelete();
            $table->boolean('is_default')->default(false);
            $table->bigInteger('price_adjustment_minor')->default(0);
            $table->foreignId('snapshot_dish_id')->nullable()->constrained('dishes')->restrictOnDelete();
            $table->foreignId('snapshot_category_id')->nullable()->constrained('dish_categories')->restrictOnDelete();
            $table->string('snapshot_offering_name')->nullable();
            $table->string('snapshot_dish_name')->nullable();
            $table->string('snapshot_category_name')->nullable();
            $table->string('snapshot_option_code')->nullable();
            $table->string('snapshot_option_label')->nullable();
            $table->string('snapshot_unit')->nullable();
            $table->decimal('snapshot_quantity', 12, 3)->nullable();
            $table->unsignedInteger('snapshot_serving_pax')->nullable();
            $table->string('snapshot_serving_claim')->nullable();
            $table->timestampsTz();
            $table->unique(['group_id', 'offering_option_id']);
            $table->index('offering_option_id');
        });
        Schema::create('group_category_rules', function (Blueprint $table) {
            $table->foreignId('group_id')->constrained('selection_groups')->restrictOnDelete();
            $table->foreignId('category_id')->constrained('dish_categories')->restrictOnDelete();
            $table->unsignedInteger('minimum_count')->default(0);
            $table->unsignedInteger('maximum_count');
            $table->timestampsTz();
            $table->primary(['group_id', 'category_id']);
            $table->index('category_id');
        });
        Schema::create('group_dish_rules', function (Blueprint $table) {
            $table->foreignId('group_id')->constrained('selection_groups')->restrictOnDelete();
            $table->foreignId('dish_id')->constrained('dishes')->restrictOnDelete();
            $table->string('effect');
            $table->timestampsTz();
            $table->primary(['group_id', 'dish_id']);
            $table->index('dish_id');
        });

        foreach (['products' => 'code', 'product_variants' => 'code', 'selection_groups' => 'group_code'] as $table => $column) {
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_code_valid CHECK ({$column} ~ '^[a-z0-9]+(_[a-z0-9]+)*$')");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_name_nonempty CHECK (length(btrim(name)) > 0)");
        }
        DB::statement("ALTER TABLE variant_versions ADD CONSTRAINT variant_versions_draft_only CHECK (status = 'draft')");
        DB::statement("ALTER TABLE variant_versions ADD CONSTRAINT variant_versions_currency_valid CHECK (currency = 'PHP')");
        DB::statement("ALTER TABLE variant_versions ADD CONSTRAINT variant_versions_price_basis_valid CHECK (price_basis IN ('per_pax', 'per_pack', 'per_set'))");
        DB::statement("ALTER TABLE variant_versions ADD CONSTRAINT variant_versions_selection_policy_valid CHECK (selection_policy IN ('uniform', 'split'))");
        DB::statement('ALTER TABLE variant_versions ADD CONSTRAINT variant_versions_price_nonnegative CHECK (base_price_minor >= 0)');
        DB::statement('ALTER TABLE variant_versions ADD CONSTRAINT variant_versions_quantities_valid CHECK (version_number >= 1 AND draft_revision >= 1 AND minimum_quantity >= 1 AND quantity_step >= 1 AND (maximum_quantity IS NULL OR maximum_quantity >= minimum_quantity) AND (minimum_override IS NULL OR minimum_override >= 1) AND (step_override IS NULL OR step_override >= 1) AND (maximum_override IS NULL OR maximum_override >= 1) AND (maximum_override_enabled OR maximum_override IS NULL) AND (subgroup_minimum IS NULL AND subgroup_step IS NULL AND subgroup_maximum IS NULL OR subgroup_minimum >= 1 AND subgroup_step >= 1 AND (subgroup_maximum IS NULL OR subgroup_maximum >= subgroup_minimum)))');
        DB::statement("ALTER TABLE selection_groups ADD CONSTRAINT selection_groups_mode_valid CHECK (mode IN ('fixed', 'select_one', 'select_many'))");
        DB::statement("ALTER TABLE selection_groups ADD CONSTRAINT selection_groups_slot_kind_valid CHECK (slot_kind IN ('rice', 'drink', 'dish', 'lechon', 'tray', 'bundle_item'))");
        DB::statement("ALTER TABLE selection_groups ADD CONSTRAINT selection_groups_category_mode_valid CHECK (category_mode IN ('free', 'restricted'))");
        DB::statement('ALTER TABLE selection_groups ADD CONSTRAINT selection_groups_counts_valid CHECK (minimum_count <= maximum_count AND maximum_count >= 1 AND inclusion_count > 0 AND (mode <> \'fixed\' OR (minimum_count = 1 AND maximum_count = 1)) AND (mode <> \'select_one\' OR maximum_count = 1))');
        DB::statement('ALTER TABLE group_options ADD CONSTRAINT group_options_adjustment_nonnegative CHECK (price_adjustment_minor >= 0)');
        DB::statement('ALTER TABLE group_category_rules ADD CONSTRAINT group_category_rules_counts_valid CHECK (minimum_count <= maximum_count AND maximum_count >= 1)');
        DB::statement("ALTER TABLE group_dish_rules ADD CONSTRAINT group_dish_rules_effect_valid CHECK (effect IN ('allow', 'deny'))");

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
            CREATE TRIGGER package_variant_family_guard BEFORE INSERT OR UPDATE ON product_variants
                FOR EACH ROW EXECUTE FUNCTION check_package_draft_family();
            CREATE TRIGGER package_version_family_guard BEFORE INSERT OR UPDATE ON variant_versions
                FOR EACH ROW EXECUTE FUNCTION check_package_draft_family();
            CREATE TRIGGER package_group_family_guard BEFORE INSERT OR UPDATE ON selection_groups
                FOR EACH ROW EXECUTE FUNCTION check_package_draft_family();
            CREATE TRIGGER package_option_family_guard BEFORE INSERT OR UPDATE ON group_options
                FOR EACH ROW EXECUTE FUNCTION check_package_draft_family();
            SQL);

        // Phase 3 establishes 10 packs as the Food Pack family default. Preserve defaults
        // that an administrator has changed since the original neutral Phase 2 seed.
        DB::table('family_order_defaults')->where('family_id', function ($query) {
            $query->select('id')->from('product_families')->where('code', 'food_pack');
        })->where('minimum_quantity', 1)->where('quantity_step', 1)
            ->whereNull('maximum_quantity')->where('enforce_minimum_floor', false)
            ->whereColumn('updated_at', 'created_at')
            ->update(['minimum_quantity' => 10, 'updated_at' => now()]);
    }

    public function down(): void
    {
        foreach (['group_dish_rules', 'group_category_rules', 'group_options', 'selection_groups', 'variant_versions', 'product_variants', 'products'] as $table) {
            Schema::dropIfExists($table);
        }
        DB::statement('DROP FUNCTION IF EXISTS check_package_draft_family()');
    }
};
