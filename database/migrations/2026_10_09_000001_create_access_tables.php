<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['roles', 'permissions'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name) {
                $table->id();
                $table->string('code')->unique();
                $table->string($name === 'roles' ? 'name' : 'description');
                $table->timestampsTz();
            });
        }
        Schema::create('user_roles', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->primary(['user_id', 'role_id']);
            $table->index('role_id');
        });
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->foreignId('permission_id')->constrained()->restrictOnDelete();
            $table->primary(['role_id', 'permission_id']);
            $table->index('permission_id');
        });
        Schema::create('user_permission_overrides', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('permission_id')->constrained()->restrictOnDelete();
            $table->string('effect');
            $table->primary(['user_id', 'permission_id']);
            $table->index('permission_id');
        });
        DB::statement("ALTER TABLE user_permission_overrides ADD CONSTRAINT override_effect_valid CHECK (effect IN ('allow', 'deny'))");
    }

    public function down(): void
    {
        foreach (['user_permission_overrides', 'role_permissions', 'user_roles', 'permissions', 'roles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
