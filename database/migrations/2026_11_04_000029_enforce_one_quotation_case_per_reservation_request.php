<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('quotations')->select('reservation_request_id')
            ->whereNotNull('reservation_request_id')->groupBy('reservation_request_id')
            ->havingRaw('count(*) > 1')->exists();
        if ($duplicates) {
            throw new RuntimeException('Cannot enforce one quotation case per reservation request until existing duplicate cases are reconciled. No quotation data was changed.');
        }
        DB::statement('CREATE UNIQUE INDEX quotations_one_case_per_reservation_request ON quotations (reservation_request_id) WHERE reservation_request_id IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS quotations_one_case_per_reservation_request');
    }
};
