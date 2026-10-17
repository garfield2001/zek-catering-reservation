<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('individual_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained('product_families')->restrictOnDelete();
            $table->foreignId('dish_id')->constrained('dishes')->restrictOnDelete();
            $table->foreignId('offering_option_id')->nullable()->constrained('offering_options')->restrictOnDelete();
            $table->string('code', 80)->unique();
            $table->uuid('creation_token')->unique();
            $table->char('creation_hash', 64);
            $table->boolean('active')->default(true);
            $table->timestampTz('archived_at')->nullable();
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->timestampsTz();
            $table->index(['family_id', 'archived_at', 'active']);
            $table->index(['dish_id', 'family_id']);
            $table->index('offering_option_id');
            $table->index('current_version_id');
        });
        Schema::create('listing_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained('individual_listings')->restrictOnDelete();
            $table->integer('version_number');
            $table->integer('draft_revision')->default(1);
            $table->string('status', 12)->default('draft');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('selling_unit', 30);
            $table->bigInteger('price_minor');
            $table->char('currency', 3)->default('PHP');
            $table->integer('minimum_quantity');
            $table->integer('quantity_step');
            $table->integer('maximum_quantity')->nullable();
            $table->jsonb('published_snapshot')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('cloned_from_id')->nullable()->constrained('listing_versions')->restrictOnDelete();
            $table->timestampsTz();
            $table->unique(['listing_id', 'version_number']);
            $table->unique(['id', 'listing_id']);
            $table->index('cloned_from_id');
            $table->index('published_by');
        });
        Schema::table('individual_listings', function (Blueprint $table) {
            $table->foreign(['current_version_id', 'id'])->references(['id', 'listing_id'])->on('listing_versions')->restrictOnDelete();
        });
        DB::unprepared(<<<'SQL'
            ALTER TABLE listing_versions ADD CONSTRAINT listing_terms_valid CHECK (
                version_number > 0 AND draft_revision > 0 AND btrim(name) <> '' AND currency = 'PHP'
                AND selling_unit IN ('item','tray','pack','piece','bottle','kg') AND price_minor >= 0
                AND minimum_quantity > 0 AND quantity_step > 0
                AND (maximum_quantity IS NULL OR maximum_quantity >= minimum_quantity)
                AND status IN ('draft','published','abandoned')
                AND ((status = 'published' AND price_minor > 0 AND published_snapshot IS NOT NULL AND jsonb_typeof(published_snapshot) = 'object' AND published_at IS NOT NULL AND published_by IS NOT NULL)
                    OR (status <> 'published' AND published_snapshot IS NULL AND published_at IS NULL AND published_by IS NULL))
            );
            CREATE UNIQUE INDEX listing_one_draft ON listing_versions(listing_id) WHERE status = 'draft';
            CREATE OR REPLACE FUNCTION guard_individual_listing() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'UPDATE' THEN
                    IF (NEW.family_id, NEW.dish_id, NEW.offering_option_id, NEW.code, NEW.creation_token, NEW.creation_hash)
                        IS DISTINCT FROM (OLD.family_id, OLD.dish_id, OLD.offering_option_id, OLD.code, OLD.creation_token, OLD.creation_hash) THEN
                        RAISE EXCEPTION 'Listing identity and source ownership are immutable' USING ERRCODE = '23514';
                    END IF;
                    IF OLD.archived_at IS NOT NULL AND (NEW.archived_at IS DISTINCT FROM OLD.archived_at OR NEW.active) THEN
                        RAISE EXCEPTION 'Archived listings cannot be restored' USING ERRCODE = '23514';
                    END IF;
                    IF OLD.current_version_id IS NOT NULL AND (NEW.current_version_id IS NULL OR
                        (SELECT version_number FROM listing_versions WHERE id = NEW.current_version_id) < (SELECT version_number FROM listing_versions WHERE id = OLD.current_version_id)) THEN
                        RAISE EXCEPTION 'Listing publication cannot move backward' USING ERRCODE = '23514';
                    END IF;
                END IF;
                IF NOT EXISTS (SELECT 1 FROM product_families WHERE id = NEW.family_id AND is_addon AND behavior_type = 'direct_sale') THEN
                    RAISE EXCEPTION 'Individual listings require an individually sold service' USING ERRCODE = '23514';
                END IF;
                IF NEW.offering_option_id IS NOT NULL AND NOT EXISTS (
                    SELECT 1 FROM offering_options o JOIN dish_offerings d ON d.id = o.offering_id
                    WHERE o.id = NEW.offering_option_id AND d.dish_id = NEW.dish_id AND d.family_id = NEW.family_id
                ) THEN RAISE EXCEPTION 'Listing serving option must belong to its food and service' USING ERRCODE = '23514'; END IF;
                IF NEW.current_version_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM listing_versions WHERE id = NEW.current_version_id AND listing_id = NEW.id AND status = 'published') THEN
                    RAISE EXCEPTION 'Current listing version must be its own publication' USING ERRCODE = '23514';
                END IF;
                IF NEW.archived_at IS NOT NULL AND NEW.active THEN RAISE EXCEPTION 'Archived listings must be disabled' USING ERRCODE = '23514'; END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER individual_listing_integrity BEFORE INSERT OR UPDATE ON individual_listings FOR EACH ROW EXECUTE FUNCTION guard_individual_listing();
            CREATE OR REPLACE FUNCTION guard_listing_version() RETURNS trigger AS $$
            DECLARE source_status text; source_listing bigint;
            BEGIN
                IF TG_OP = 'DELETE' THEN RAISE EXCEPTION 'Listing history must be retained' USING ERRCODE = '23514'; END IF;
                IF TG_OP = 'UPDATE' THEN
                    IF OLD.status <> 'draft' OR (NEW.listing_id, NEW.version_number, NEW.cloned_from_id) IS DISTINCT FROM (OLD.listing_id, OLD.version_number, OLD.cloned_from_id) THEN
                        RAISE EXCEPTION 'Published listing facts and version identity are immutable' USING ERRCODE = '23514';
                    END IF;
                END IF;
                PERFORM 1 FROM individual_listings WHERE id = NEW.listing_id FOR UPDATE;
                IF NEW.cloned_from_id IS NOT NULL THEN
                    SELECT status, listing_id INTO source_status, source_listing FROM listing_versions WHERE id = NEW.cloned_from_id;
                    IF source_status IS DISTINCT FROM 'published' OR source_listing IS DISTINCT FROM NEW.listing_id OR NEW.version_number <= (SELECT version_number FROM listing_versions WHERE id = NEW.cloned_from_id) THEN
                        RAISE EXCEPTION 'A listing revision must clone its own older publication' USING ERRCODE = '23514';
                    END IF;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER listing_version_integrity BEFORE INSERT OR UPDATE OR DELETE ON listing_versions FOR EACH ROW EXECUTE FUNCTION guard_listing_version();
            SQL);
        foreach (['listing.view' => 'View individual listings and current published details', 'listing.manage' => 'Create and edit individual listing drafts', 'listing.pricing.manage' => 'Set individual listing prices and purchase quantities', 'listing.publish' => 'Publish individual listing revisions', 'listing.history.view' => 'Inspect and clone individual listing publication history', 'listing.lifecycle.manage' => 'Disable and archive individual listings'] as $code => $description) {
            DB::table('permissions')->insertOrIgnore(['code' => $code, 'description' => $description, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        if (DB::table('individual_listings')->exists()) {
            throw new RuntimeException('Export and preserve individual listing records before removing this capability.');
        }
        $permissions = DB::table('permissions')->where('code', 'like', 'listing.%')->pluck('id');
        if (DB::table('role_permissions')->whereIn('permission_id', $permissions)->exists() || DB::table('user_permission_overrides')->whereIn('permission_id', $permissions)->exists()) {
            throw new RuntimeException('Remove assigned listing permissions before rollback.');
        }
        Schema::table('individual_listings', fn (Blueprint $table) => $table->dropForeign(['current_version_id', 'id']));
        Schema::dropIfExists('listing_versions');
        Schema::dropIfExists('individual_listings');
        DB::statement('DROP FUNCTION guard_listing_version()');
        DB::statement('DROP FUNCTION guard_individual_listing()');
        DB::table('permissions')->whereIn('id', $permissions)->delete();
    }
};
