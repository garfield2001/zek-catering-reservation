<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // The original package schema restricted food portions to a small fixed
        // list. Service presentation and size choices already use the shared
        // Measurement Unit catalog, so food portions must do the same.
        DB::unprepared(<<<'SQL'
            ALTER TABLE package_version_food_items
                DROP CONSTRAINT package_food_measurement_valid;
            ALTER TABLE package_version_food_items
                ADD CONSTRAINT package_food_measurement_valid CHECK (
                    (included_quantity IS NULL AND included_unit IS NULL)
                    OR (included_quantity IS NOT NULL AND included_quantity > 0 AND included_unit IS NOT NULL)
                );
            SQL);
    }

    public function down(): void
    {
        if (DB::table('package_version_food_items')
            ->whereNotNull('included_unit')
            ->whereNotIn('included_unit', ['kg', 'tray', 'portion', 'piece', 'bottle', 'pack'])
            ->exists()) {
            throw new RuntimeException('Custom food portion units exist and cannot be restored to the retired fixed list.');
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE package_version_food_items
                DROP CONSTRAINT package_food_measurement_valid;
            ALTER TABLE package_version_food_items
                ADD CONSTRAINT package_food_measurement_valid CHECK (
                    (included_quantity IS NULL AND included_unit IS NULL)
                    OR (included_quantity IS NOT NULL AND included_quantity > 0 AND included_unit IS NOT NULL
                        AND included_unit IN ('kg', 'tray', 'portion', 'piece', 'bottle', 'pack'))
                );
            SQL);
    }
};
