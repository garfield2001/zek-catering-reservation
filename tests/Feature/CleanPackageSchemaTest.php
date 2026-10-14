<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CleanPackageSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_clean_baseline_contains_package_versions_without_legacy_variant_objects(): void
    {
        $this->assertTrue(Schema::hasTable('products'));
        $this->assertTrue(Schema::hasTable('package_versions'));
        $this->assertTrue(Schema::hasTable('selection_groups'));
        $this->assertTrue(Schema::hasColumn('selection_groups', 'package_version_id'));
        $this->assertFalse(Schema::hasTable('product_variants'));
        $this->assertFalse(Schema::hasTable('variant_versions'));

        $legacyColumns = DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where(function ($query): void {
                $query->where('table_name', 'like', '%variant%')
                    ->orWhere('column_name', 'like', '%variant%');
            })
            ->count();
        $this->assertSame(0, $legacyColumns);

        $legacyIndexes = DB::table('pg_indexes')
            ->where('schemaname', 'public')
            ->where(function ($query): void {
                $query->where('indexname', 'like', '%variant%')
                    ->orWhere('tablename', 'like', '%variant%');
            })
            ->count();
        $this->assertSame(0, $legacyIndexes);

        $legacyTriggers = DB::table('pg_trigger as trigger')
            ->join('pg_class as relation', 'relation.oid', '=', 'trigger.tgrelid')
            ->where('trigger.tgisinternal', false)
            ->where(function ($query): void {
                $query->where('trigger.tgname', 'like', '%variant%')
                    ->orWhere('relation.relname', 'like', '%variant%');
            })
            ->count();
        $this->assertSame(0, $legacyTriggers);

        $legacyFunctions = DB::table('information_schema.routines')
            ->where('routine_schema', 'public')
            ->where(function ($query): void {
                $query->where('routine_name', 'like', '%variant%')
                    ->orWhere('routine_name', 'like', '%product_variant%');
            })
            ->count();

        $this->assertSame(0, $legacyFunctions);
        $this->assertSame(0, DB::table('pg_constraint')->where('conname', 'like', '%variant%')->count());
        $this->assertFalse(Schema::hasColumn('package_versions', 'minimum_override'));
        $this->assertFalse(Schema::hasColumn('package_versions', 'maximum_override_enabled'));
        $this->assertTrue(Schema::hasColumn('package_versions', 'estimated_capacity'));
        $this->assertTrue(Schema::hasColumn('package_version_food_items', 'always_include'));

        $this->assertTrue(DB::table('pg_constraint')
            ->where('conname', 'products_current_version_fk')
            ->exists());
        $this->assertTrue(DB::table('pg_indexes')
            ->where('indexname', 'package_versions_one_active_draft')
            ->exists());
    }
}
