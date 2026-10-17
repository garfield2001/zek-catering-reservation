<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('measurement_units', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('abbreviation', 20);
            $table->boolean('active')->default(true);
            $table->timestampsTz();
            $table->index(['active', 'name']);
        });
        DB::statement('CREATE UNIQUE INDEX measurement_units_name_unique ON measurement_units (lower(btrim(name)))');
        DB::statement('CREATE UNIQUE INDEX measurement_units_abbreviation_unique ON measurement_units (lower(btrim(abbreviation)))');
        DB::statement('ALTER TABLE measurement_units ADD CONSTRAINT measurement_units_labels_present CHECK (length(btrim(name)) > 0 AND length(btrim(abbreviation)) > 0)');
    }

    public function down(): void
    {
        if (DB::table('measurement_units')->exists()) {
            throw new RuntimeException('Preserve Measurement Units before removing their schema.');
        }
        Schema::dropIfExists('measurement_units');
    }
};
