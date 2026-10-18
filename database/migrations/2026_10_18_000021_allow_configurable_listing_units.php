<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->terms("length(btrim(selling_unit)) BETWEEN 1 AND 30 AND selling_unit = btrim(selling_unit) AND selling_unit !~ '[[:cntrl:]]'");
    }

    public function down(): void
    {
        if (DB::table('listing_versions')->whereNotIn('selling_unit', ['item', 'tray', 'pack', 'piece', 'bottle', 'kg'])->exists()) {
            throw new RuntimeException('Preserve configured listing units before restoring the legacy unit restriction.');
        }
        $this->terms("selling_unit IN ('item','tray','pack','piece','bottle','kg')");
    }

    private function terms(string $unitCheck): void
    {
        // Only the unit predicate changes; all existing price, quantity and lifecycle rules remain.
        DB::unprepared(<<<SQL
            ALTER TABLE listing_versions DROP CONSTRAINT listing_terms_valid;
            ALTER TABLE listing_versions ADD CONSTRAINT listing_terms_valid CHECK (
                version_number > 0 AND draft_revision > 0 AND btrim(name) <> '' AND currency = 'PHP'
                AND ($unitCheck) AND price_minor >= 0
                AND minimum_quantity > 0 AND quantity_step > 0
                AND (maximum_quantity IS NULL OR maximum_quantity >= minimum_quantity)
                AND status IN ('draft','published','abandoned')
                AND ((status = 'published' AND price_minor > 0 AND published_snapshot IS NOT NULL AND jsonb_typeof(published_snapshot) = 'object' AND published_at IS NOT NULL AND published_by IS NOT NULL)
                    OR (status <> 'published' AND published_snapshot IS NULL AND published_at IS NULL AND published_by IS NULL))
            );
            SQL);
    }
};
