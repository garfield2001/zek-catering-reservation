<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('avatar_disk', 40)->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('avatar_thumbnail_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['avatar_disk', 'avatar_path', 'avatar_thumbnail_path']);
        });
    }
};
