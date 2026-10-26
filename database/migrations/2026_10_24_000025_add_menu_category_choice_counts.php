<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_version_required_categories', function ($table): void {
            $table->unsignedInteger('minimum_count')->default(1);
            $table->unsignedInteger('maximum_count')->default(1);
            $table->boolean('maximum_count_explicit')->default(false);
        });

        // Before explicit ranges existed, a required category meant “at least
        // one”; the package's exact total was its effective upper bound.
        DB::statement('UPDATE package_version_required_categories AS rules SET maximum_count = versions.total_selection_count FROM package_versions AS versions WHERE versions.id = rules.package_version_id');

        DB::statement('ALTER TABLE package_version_required_categories ADD CONSTRAINT package_required_category_choice_counts_valid CHECK (minimum_count <= maximum_count AND maximum_count >= 1)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE package_version_required_categories DROP CONSTRAINT IF EXISTS package_required_category_choice_counts_valid');

        Schema::table('package_version_required_categories', function ($table): void {
            $table->dropColumn(['minimum_count', 'maximum_count', 'maximum_count_explicit']);
        });
    }
};
