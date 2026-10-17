<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_food_size_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('package_version_id')->constrained('package_versions')->restrictOnDelete();
            $table->foreignId('dish_id')->constrained('dishes')->restrictOnDelete();
            $table->foreignId('measurement_unit_id')->constrained('measurement_units')->restrictOnDelete();
            $table->unique(['package_version_id', 'dish_id']);
            $table->foreign(['package_version_id', 'dish_id'])->references(['package_version_id', 'dish_id'])->on('package_version_food_items')->restrictOnDelete();
            $table->index('dish_id');
            $table->index('measurement_unit_id');
            $table->timestampsTz();
        });
        Schema::create('package_food_size_choices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('size_group_id')->constrained('package_food_size_groups')->restrictOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->bigInteger('extra_price_minor')->default(0);
            $table->boolean('is_default')->default(false);
            $table->timestampsTz();
            $table->unique(['size_group_id', 'quantity']);
        });
        DB::unprepared(<<<'SQL'
            ALTER TABLE package_food_size_choices ADD CONSTRAINT package_food_size_values CHECK (quantity > 0 AND extra_price_minor >= 0 AND (NOT is_default OR extra_price_minor = 0));
            CREATE UNIQUE INDEX package_food_size_one_default ON package_food_size_choices (size_group_id) WHERE is_default;
            CREATE OR REPLACE FUNCTION guard_package_food_sizes() RETURNS trigger AS $$
            DECLARE owner_id bigint; owner_status text; behavior text;
            BEGIN
                IF TG_TABLE_NAME = 'package_food_size_groups' THEN
                    owner_id := CASE WHEN TG_OP = 'DELETE' THEN OLD.package_version_id ELSE NEW.package_version_id END;
                    IF TG_OP = 'UPDATE' AND (NEW.package_version_id, NEW.dish_id) IS DISTINCT FROM (OLD.package_version_id, OLD.dish_id) THEN
                        RAISE EXCEPTION 'Size group ownership is immutable' USING ERRCODE = '23514';
                    END IF;
                ELSE
                    SELECT package_version_id INTO owner_id FROM package_food_size_groups WHERE id = CASE WHEN TG_OP = 'DELETE' THEN OLD.size_group_id ELSE NEW.size_group_id END;
                    IF TG_OP = 'UPDATE' AND NEW.size_group_id <> OLD.size_group_id THEN RAISE EXCEPTION 'Size choice ownership is immutable' USING ERRCODE = '23514'; END IF;
                END IF;
                SELECT status INTO owner_status FROM package_versions WHERE id = owner_id FOR UPDATE;
                IF owner_status IS DISTINCT FROM 'draft' THEN RAISE EXCEPTION 'Only Draft size choices may change' USING ERRCODE = '23514'; END IF;
                SELECT f.behavior_type INTO behavior FROM package_versions v JOIN product_families f ON f.id = v.family_id WHERE v.id = owner_id;
                IF behavior = 'main_package' THEN RAISE EXCEPTION 'Main Catering has no food size upgrades' USING ERRCODE = '23514'; END IF;
                IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER package_food_size_group_guard BEFORE INSERT OR UPDATE OR DELETE ON package_food_size_groups FOR EACH ROW EXECUTE FUNCTION guard_package_food_sizes();
            CREATE TRIGGER package_food_size_choice_guard BEFORE INSERT OR UPDATE OR DELETE ON package_food_size_choices FOR EACH ROW EXECUTE FUNCTION guard_package_food_sizes();
            CREATE OR REPLACE FUNCTION check_package_food_size_default() RETURNS trigger AS $$
            DECLARE group_id bigint;
            BEGIN
                IF TG_TABLE_NAME = 'package_food_size_groups' THEN group_id := NEW.id;
                ELSE group_id := CASE WHEN TG_OP = 'DELETE' THEN OLD.size_group_id ELSE NEW.size_group_id END; END IF;
                IF EXISTS (SELECT 1 FROM package_food_size_groups WHERE id = group_id) AND (SELECT count(*) FROM package_food_size_choices WHERE size_group_id = group_id AND is_default) <> 1 THEN
                    RAISE EXCEPTION 'Each size group requires exactly one included default' USING ERRCODE = '23514';
                END IF;
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;
            CREATE CONSTRAINT TRIGGER package_food_size_group_default AFTER INSERT OR UPDATE ON package_food_size_groups DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION check_package_food_size_default();
            CREATE CONSTRAINT TRIGGER package_food_size_choice_default AFTER INSERT OR UPDATE OR DELETE ON package_food_size_choices DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION check_package_food_size_default();
            SQL);
    }

    public function down(): void
    {
        if (DB::table('package_food_size_groups')->exists()) {
            throw new RuntimeException('Preserve package size choices before removing their schema.');
        }
        Schema::dropIfExists('package_food_size_choices');
        Schema::dropIfExists('package_food_size_groups');
        DB::statement('DROP FUNCTION IF EXISTS guard_package_food_sizes()');
        DB::statement('DROP FUNCTION IF EXISTS check_package_food_size_default()');
    }
};
