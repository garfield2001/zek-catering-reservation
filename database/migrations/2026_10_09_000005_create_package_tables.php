<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('family_id')->constrained('product_families')->restrictOnDelete();
            $table->string('code', 80);
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestampTz('archived_at')->nullable();
            $table->uuid('creation_token')->nullable()->unique();
            $table->foreignId('current_version_id')->nullable();
            $table->timestampsTz();
            $table->unique(['family_id', 'code']);
            $table->index(['family_id', 'sort_order', 'id']);
        });

        Schema::create('package_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('package_product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('family_id')->constrained('product_families')->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('status')->default('draft');
            $table->unsignedInteger('draft_revision')->default(1);
            $table->foreignId('cloned_from_id')->nullable()->constrained('package_versions')->restrictOnDelete();
            $table->bigInteger('base_price_minor');
            $table->char('currency', 3)->default('PHP');
            $table->string('price_basis');
            $table->string('selection_policy')->default('uniform');
            $table->unsignedInteger('total_selection_count')->nullable();
            $table->unsignedInteger('estimated_capacity')->nullable();
            $table->unsignedInteger('minimum_quantity');
            $table->unsignedInteger('quantity_step');
            $table->unsignedInteger('maximum_quantity')->nullable();
            $table->unsignedInteger('subgroup_minimum')->nullable();
            $table->unsignedInteger('subgroup_step')->nullable();
            $table->unsignedInteger('subgroup_maximum')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->jsonb('published_snapshot')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('abandoned_at')->nullable();
            $table->foreignId('abandoned_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
            $table->unique(['package_product_id', 'version_number']);
            $table->unique(['package_product_id', 'id']);
            $table->index(['family_id', 'status']);
            $table->index(['package_product_id', 'version_number']);
        });

        Schema::create('selection_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('package_version_id')->constrained('package_versions')->restrictOnDelete();
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
            $table->unique(['package_version_id', 'group_code']);
            $table->index(['package_version_id', 'sort_order']);
        });

        Schema::create('group_options', function (Blueprint $table): void {
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

        Schema::create('group_category_rules', function (Blueprint $table): void {
            $table->foreignId('group_id')->constrained('selection_groups')->restrictOnDelete();
            $table->foreignId('category_id')->constrained('dish_categories')->restrictOnDelete();
            $table->unsignedInteger('minimum_count')->default(0);
            $table->unsignedInteger('maximum_count');
            $table->timestampsTz();
            $table->primary(['group_id', 'category_id']);
            $table->index('category_id');
        });

        Schema::create('group_dish_rules', function (Blueprint $table): void {
            $table->foreignId('group_id')->constrained('selection_groups')->restrictOnDelete();
            $table->foreignId('dish_id')->constrained('dishes')->restrictOnDelete();
            $table->string('effect');
            $table->timestampsTz();
            $table->primary(['group_id', 'dish_id']);
            $table->index('dish_id');
        });

        Schema::create('package_version_food_items', function (Blueprint $table): void {
            $table->foreignId('package_version_id')->constrained('package_versions')->restrictOnDelete();
            $table->foreignId('dish_id')->constrained('dishes')->restrictOnDelete();
            $table->boolean('is_fixed')->default(false);
            $table->boolean('always_include')->default(false);
            $table->decimal('included_quantity', 12, 3)->nullable();
            $table->string('included_unit')->nullable();
            $table->bigInteger('price_adjustment_minor')->default(0);
            $table->timestampsTz();
            $table->primary(['package_version_id', 'dish_id']);
            $table->index(['package_version_id', 'is_fixed']);
        });

        Schema::create('package_version_required_categories', function (Blueprint $table): void {
            $table->foreignId('package_version_id')->constrained('package_versions')->restrictOnDelete();
            $table->foreignId('category_id')->constrained('dish_categories')->restrictOnDelete();
            $table->timestampsTz();
            $table->primary(['package_version_id', 'category_id']);
        });

        foreach (['products' => 'code', 'selection_groups' => 'group_code'] as $table => $column) {
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_code_valid CHECK ({$column} ~ '^[a-z0-9]+(_[a-z0-9]+)*$')");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_name_nonempty CHECK (length(btrim(name)) > 0)");
        }
        DB::statement("ALTER TABLE package_versions ADD CONSTRAINT package_versions_status_valid CHECK (status IN ('draft','published','abandoned'))");
        DB::statement("ALTER TABLE package_versions ADD CONSTRAINT package_versions_lifecycle_valid CHECK ((status = 'draft' AND published_snapshot IS NULL AND published_at IS NULL AND published_by IS NULL AND abandoned_at IS NULL AND abandoned_by IS NULL) OR (status = 'published' AND published_snapshot IS NOT NULL AND published_at IS NOT NULL AND published_by IS NOT NULL AND abandoned_at IS NULL AND abandoned_by IS NULL) OR (status = 'abandoned' AND published_snapshot IS NULL AND published_at IS NULL AND abandoned_at IS NOT NULL AND abandoned_by IS NOT NULL))");
        DB::statement("ALTER TABLE package_versions ADD CONSTRAINT package_versions_currency_valid CHECK (currency = 'PHP')");
        DB::statement("ALTER TABLE package_versions ADD CONSTRAINT package_versions_price_basis_valid CHECK (price_basis IN ('per_pax','per_pack','per_set'))");
        DB::statement("ALTER TABLE package_versions ADD CONSTRAINT package_versions_selection_policy_valid CHECK (selection_policy IN ('uniform','split'))");
        DB::statement("ALTER TABLE package_version_food_items ADD CONSTRAINT package_food_measurement_valid CHECK ((included_quantity IS NULL AND included_unit IS NULL) OR (included_quantity IS NOT NULL AND included_quantity > 0 AND included_unit IS NOT NULL AND included_unit IN ('kg','tray','portion','piece','bottle','pack')))");
        DB::statement('ALTER TABLE package_version_food_items ADD CONSTRAINT package_food_price_valid CHECK (price_adjustment_minor >= 0)');
        DB::statement('ALTER TABLE package_versions ADD CONSTRAINT package_capacity_valid CHECK (estimated_capacity IS NULL OR estimated_capacity > 0)');
        DB::statement('ALTER TABLE package_versions ADD CONSTRAINT package_versions_price_nonnegative CHECK (base_price_minor >= 0)');
        DB::statement('ALTER TABLE package_versions ADD CONSTRAINT package_versions_total_selection_valid CHECK (total_selection_count IS NULL OR total_selection_count >= 1)');
        DB::statement('ALTER TABLE package_versions ADD CONSTRAINT package_versions_quantities_valid CHECK (version_number >= 1 AND draft_revision >= 1 AND minimum_quantity >= 1 AND quantity_step >= 1 AND (maximum_quantity IS NULL OR maximum_quantity >= minimum_quantity) AND (subgroup_minimum IS NULL AND subgroup_step IS NULL AND subgroup_maximum IS NULL OR subgroup_minimum >= 1 AND subgroup_step >= 1 AND (subgroup_maximum IS NULL OR subgroup_maximum >= subgroup_minimum)))');
        DB::statement("ALTER TABLE selection_groups ADD CONSTRAINT selection_groups_mode_valid CHECK (mode IN ('fixed','select_one','select_many'))");
        DB::statement("ALTER TABLE selection_groups ADD CONSTRAINT selection_groups_slot_kind_valid CHECK (slot_kind IN ('item','rice','drink','dish','lechon','tray','bundle_item'))");
        DB::statement("ALTER TABLE selection_groups ADD CONSTRAINT selection_groups_category_mode_valid CHECK (category_mode IN ('free','restricted'))");
        DB::statement('ALTER TABLE selection_groups ADD CONSTRAINT selection_groups_counts_valid CHECK (minimum_count <= maximum_count AND maximum_count >= 1 AND inclusion_count > 0 AND (mode <> \'fixed\' OR (minimum_count = 1 AND maximum_count = 1)) AND (mode <> \'select_one\' OR maximum_count = 1))');
        DB::statement('ALTER TABLE group_options ADD CONSTRAINT group_options_adjustment_nonnegative CHECK (price_adjustment_minor >= 0)');
        DB::statement('ALTER TABLE group_category_rules ADD CONSTRAINT group_category_rules_counts_valid CHECK (minimum_count <= maximum_count AND maximum_count >= 1)');
        DB::statement("ALTER TABLE group_dish_rules ADD CONSTRAINT group_dish_rules_effect_valid CHECK (effect IN ('allow','deny'))");
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_archive_valid CHECK (archived_at IS NULL OR active = false)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_current_version_fk FOREIGN KEY (id, current_version_id) REFERENCES package_versions (package_product_id, id) DEFERRABLE INITIALLY IMMEDIATE');
        DB::statement("CREATE UNIQUE INDEX package_versions_one_active_draft ON package_versions (package_product_id) WHERE status = 'draft'");
    }

    public function down(): void
    {
        // The current-publication pointer is a composite FK from products to
        // package_versions; remove it before dropping the referenced table.
        DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS products_current_version_fk');
        foreach (['package_version_required_categories', 'package_version_food_items', 'group_dish_rules', 'group_category_rules', 'group_options', 'selection_groups', 'package_versions', 'products'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
