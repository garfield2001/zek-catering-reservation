<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dish_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestampsTz();
        });
        DB::statement('ALTER TABLE dish_categories ADD COLUMN name_key text GENERATED ALWAYS AS (lower(btrim(name))) STORED');
        DB::statement('CREATE UNIQUE INDEX dish_categories_name_key_unique ON dish_categories (name_key)');
        DB::statement("ALTER TABLE dish_categories ADD CONSTRAINT dish_categories_code_valid CHECK (code ~ '^[a-z0-9]+(_[a-z0-9]+)*$')");
        DB::statement('ALTER TABLE dish_categories ADD CONSTRAINT dish_categories_name_nonempty CHECK (length(btrim(name)) > 0)');

        Schema::create('product_families', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('is_addon');
            $table->boolean('active')->default(true);
            $table->timestampsTz();
        });
        DB::statement("ALTER TABLE product_families ADD CONSTRAINT product_families_code_valid CHECK (code IN ('main_catering', 'belly_lechon', 'food_pack', 'food_tray', 'pica_pica'))");

        Schema::create('family_order_defaults', function (Blueprint $table) {
            $table->foreignId('family_id')->primary()->constrained('product_families')->restrictOnDelete();
            $table->unsignedInteger('minimum_quantity');
            $table->unsignedInteger('quantity_step');
            $table->unsignedInteger('maximum_quantity')->nullable();
            $table->boolean('enforce_minimum_floor')->default(false);
            $table->timestampsTz();
        });
        DB::statement('ALTER TABLE family_order_defaults ADD CONSTRAINT family_order_defaults_valid CHECK (minimum_quantity >= 1 AND quantity_step >= 1 AND (maximum_quantity IS NULL OR maximum_quantity >= minimum_quantity))');

        Schema::create('dishes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('category_id')->constrained('dish_categories')->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestampsTz();
            $table->index(['category_id', 'active']);
        });
        DB::statement('ALTER TABLE dishes ADD COLUMN name_key text GENERATED ALWAYS AS (lower(btrim(name))) STORED');
        DB::statement('CREATE UNIQUE INDEX dishes_category_name_key_unique ON dishes (category_id, name_key)');
        DB::statement("ALTER TABLE dishes ADD CONSTRAINT dishes_code_valid CHECK (code ~ '^[a-z0-9]+(_[a-z0-9]+)*$')");
        DB::statement('ALTER TABLE dishes ADD CONSTRAINT dishes_name_nonempty CHECK (length(btrim(name)) > 0)');

        Schema::create('dish_family_eligibilities', function (Blueprint $table) {
            $table->foreignId('dish_id')->constrained('dishes')->restrictOnDelete();
            $table->foreignId('family_id')->constrained('product_families')->restrictOnDelete();
            $table->boolean('active')->default(true);
            $table->timestampsTz();
            $table->primary(['dish_id', 'family_id']);
            $table->index(['family_id', 'active']);
        });

        Schema::create('dish_offerings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dish_id');
            $table->foreignId('family_id');
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestampsTz();
            $table->unique(['dish_id', 'family_id', 'code']);
            $table->index(['dish_id', 'family_id']);
            $table->index('family_id');
        });
        DB::statement('ALTER TABLE dish_offerings ADD CONSTRAINT dish_offerings_eligibility_fk FOREIGN KEY (dish_id, family_id) REFERENCES dish_family_eligibilities (dish_id, family_id) ON DELETE RESTRICT');
        DB::statement("ALTER TABLE dish_offerings ADD CONSTRAINT dish_offerings_code_valid CHECK (code ~ '^[a-z0-9]+(_[a-z0-9]+)*$')");
        DB::statement('ALTER TABLE dish_offerings ADD CONSTRAINT dish_offerings_name_nonempty CHECK (length(btrim(name)) > 0)');

        Schema::create('offering_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offering_id')->constrained('dish_offerings')->restrictOnDelete();
            $table->string('option_code');
            $table->string('label');
            $table->string('unit');
            $table->decimal('quantity', 12, 3);
            $table->unsignedInteger('serving_pax')->nullable();
            $table->string('serving_claim')->nullable();
            $table->boolean('active')->default(true);
            $table->timestampsTz();
            $table->unique(['offering_id', 'option_code']);
            $table->index(['offering_id', 'active']);
        });
        DB::statement("ALTER TABLE offering_options ADD CONSTRAINT offering_options_code_valid CHECK (option_code ~ '^[a-z0-9]+(_[a-z0-9]+)*$')");
        DB::statement("ALTER TABLE offering_options ADD CONSTRAINT offering_options_unit_valid CHECK (unit IN ('kg', 'tray', 'portion', 'piece', 'bottle', 'pack'))");
        DB::statement('ALTER TABLE offering_options ADD CONSTRAINT offering_options_quantity_valid CHECK (quantity > 0)');
        DB::statement('ALTER TABLE offering_options ADD CONSTRAINT offering_options_serving_pax_valid CHECK (serving_pax IS NULL OR serving_pax > 0)');
        DB::statement('ALTER TABLE offering_options ADD CONSTRAINT offering_options_label_nonempty CHECK (length(btrim(label)) > 0)');
    }

    public function down(): void
    {
        foreach (['offering_options', 'dish_offerings', 'dish_family_eligibilities', 'dishes', 'family_order_defaults', 'product_families', 'dish_categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
