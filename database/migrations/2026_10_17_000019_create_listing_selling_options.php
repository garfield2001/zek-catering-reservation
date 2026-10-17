<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listing_versions', function (Blueprint $table): void {
            $table->boolean('has_selling_options')->default(false);
            $table->boolean('uses_food_name')->default(false);
        });
        Schema::create('listing_selling_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('listing_version_id')->constrained('listing_versions')->restrictOnDelete();
            $table->string('label', 100);
            $table->bigInteger('price_minor');
            $table->integer('sort_order');
            $table->unique(['listing_version_id', 'sort_order']);
        });
        DB::unprepared(<<<'SQL'
            ALTER TABLE listing_selling_options ADD CONSTRAINT listing_selling_option_values CHECK (length(btrim(label)) > 0 AND price_minor >= 0 AND sort_order >= 0);
            CREATE UNIQUE INDEX listing_selling_option_label_unique ON listing_selling_options(listing_version_id, lower(btrim(label)));
            CREATE OR REPLACE FUNCTION guard_listing_selling_option() RETURNS trigger AS $$
            DECLARE version_id bigint; owner_id bigint; owner_status text;
            BEGIN
                version_id := CASE WHEN TG_OP = 'DELETE' THEN OLD.listing_version_id ELSE NEW.listing_version_id END;
                SELECT listing_id INTO owner_id FROM listing_versions WHERE id = version_id;
                PERFORM 1 FROM individual_listings WHERE id = owner_id FOR UPDATE;
                SELECT status INTO owner_status FROM listing_versions WHERE id = version_id FOR UPDATE;
                IF owner_status IS DISTINCT FROM 'draft' THEN RAISE EXCEPTION 'Only Draft selling options may change' USING ERRCODE = '23514'; END IF;
                IF TG_OP = 'UPDATE' AND NEW.listing_version_id <> OLD.listing_version_id THEN RAISE EXCEPTION 'Selling option ownership is immutable' USING ERRCODE = '23514'; END IF;
                IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER listing_selling_option_guard BEFORE INSERT OR UPDATE OR DELETE ON listing_selling_options FOR EACH ROW EXECUTE FUNCTION guard_listing_selling_option();
            CREATE OR REPLACE FUNCTION check_listing_selling_options() RETURNS trigger AS $$
            DECLARE version_id bigint; version_record record; option_count bigint; lowest_price bigint;
            BEGIN
                IF TG_TABLE_NAME = 'listing_versions' THEN version_id := NEW.id;
                ELSE version_id := CASE WHEN TG_OP = 'DELETE' THEN OLD.listing_version_id ELSE NEW.listing_version_id END; END IF;
                SELECT * INTO version_record FROM listing_versions WHERE id = version_id;
                SELECT count(*), min(price_minor) INTO option_count, lowest_price FROM listing_selling_options WHERE listing_version_id = version_id;
                IF version_record.has_selling_options THEN
                    IF option_count = 0 OR lowest_price IS DISTINCT FROM version_record.price_minor OR (version_record.status = 'published' AND lowest_price <= 0) THEN
                        RAISE EXCEPTION 'Grouped listing requires options and an accurate starting price' USING ERRCODE = '23514';
                    END IF;
                ELSIF option_count <> 0 THEN
                    RAISE EXCEPTION 'Legacy listing cannot have untracked selling options' USING ERRCODE = '23514';
                END IF;
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;
            CREATE CONSTRAINT TRIGGER listing_version_selling_options AFTER INSERT OR UPDATE ON listing_versions DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION check_listing_selling_options();
            CREATE CONSTRAINT TRIGGER listing_selling_options_complete AFTER INSERT OR UPDATE OR DELETE ON listing_selling_options DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION check_listing_selling_options();
            SQL);
    }

    public function down(): void
    {
        if (DB::table('listing_versions')->where('has_selling_options', true)->orWhere('uses_food_name', true)->exists()) {
            throw new RuntimeException('Preserve grouped selling options and display-name intent before rollback.');
        }
        DB::statement('DROP TRIGGER listing_version_selling_options ON listing_versions');
        Schema::drop('listing_selling_options');
        DB::statement('DROP FUNCTION check_listing_selling_options()');
        DB::statement('DROP FUNCTION guard_listing_selling_option()');
        Schema::table('listing_versions', fn (Blueprint $table) => $table->dropColumn(['has_selling_options', 'uses_food_name']));
    }
};
