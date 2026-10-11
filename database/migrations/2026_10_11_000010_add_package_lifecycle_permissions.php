<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['package.publish' => 'Publish package variant versions',
            'package.history.view' => 'View published and abandoned package history',
            'package.lifecycle.manage' => 'Disable and archive package offerings'] as $code => $description) {
            DB::table('permissions')->insertOrIgnore(['code' => $code, 'description' => $description,
                'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        foreach (['package.publish', 'package.history.view', 'package.lifecycle.manage'] as $code) {
            $id = DB::table('permissions')->where('code', $code)->value('id');
            if ($id !== null && (DB::table('user_permission_overrides')->where('permission_id', $id)->exists()
                || DB::table('role_permissions')->where('permission_id', $id)->exists())) {
                throw new RuntimeException("Cannot remove assigned permission {$code}.");
            }
        }
        DB::table('permissions')->whereIn('code', ['package.publish', 'package.history.view', 'package.lifecycle.manage'])->delete();
    }
};
