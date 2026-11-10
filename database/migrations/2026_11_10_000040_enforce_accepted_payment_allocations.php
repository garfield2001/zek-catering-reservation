<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION validate_payment_allocation_accepted_version() RETURNS trigger AS $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1
                    FROM quotation_versions
                    INNER JOIN quotation_acceptances
                        ON quotation_acceptances.quotation_version_id = quotation_versions.id
                    WHERE quotation_versions.id = NEW.quotation_version_id
                      AND quotation_versions.status = 'accepted'
                ) THEN
                    RAISE EXCEPTION 'payment allocations require an accepted quotation version' USING ERRCODE = '23514';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER payment_allocations_accepted_version_guard
                BEFORE INSERT OR UPDATE ON payment_allocations
                FOR EACH ROW EXECUTE FUNCTION validate_payment_allocation_accepted_version();
            SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Accepted payment allocation integrity is forward-only.');
    }
};
