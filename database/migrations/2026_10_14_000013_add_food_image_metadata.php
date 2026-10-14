<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dishes', function (Blueprint $table): void {
            $table->string('image_disk', 50)->nullable();
            $table->string('image_path')->nullable();
            $table->string('image_thumbnail_path')->nullable();
            $table->integer('image_width')->nullable();
            $table->integer('image_height')->nullable();
        });
        DB::statement('ALTER TABLE dishes ADD CONSTRAINT dishes_image_metadata_check CHECK ((image_disk IS NULL AND image_path IS NULL AND image_thumbnail_path IS NULL AND image_width IS NULL AND image_height IS NULL) OR (image_disk IS NOT NULL AND image_path IS NOT NULL AND image_thumbnail_path IS NOT NULL AND image_width > 0 AND image_height > 0 AND image_width IS NOT NULL AND image_height IS NOT NULL))');
    }

    public function down(): void
    {
        if (DB::table('dishes')->whereNotNull('image_path')->exists()) {
            throw new RuntimeException('Preserve food images before removing their metadata.');
        }
        DB::statement('ALTER TABLE dishes DROP CONSTRAINT dishes_image_metadata_check');
        Schema::table('dishes', fn (Blueprint $table) => $table->dropColumn(['image_disk', 'image_path', 'image_thumbnail_path', 'image_width', 'image_height']));
    }
};
