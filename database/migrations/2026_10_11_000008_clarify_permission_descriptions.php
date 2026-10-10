<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const DESCRIPTIONS = [
        'dashboard.view' => ['View the dashboard', 'View dashboard'],
        'staff.view' => ['View staff accounts', 'View team members'],
        'staff.manage' => ['Create and update staff accounts', 'Create and update team member accounts'],
        'access.manage' => ['Manage staff permission overrides', 'Assign predefined Staff permissions'],
        'audit.view' => ['View audit history', 'View Activity History'],
        'catalog.view' => ['View the master catalog', 'View food items, categories and offerings'],
        'catalog.manage' => ['Manage dishes, categories, eligibility, offerings and serving options', 'Manage food items, categories, eligibility, offerings and serving options'],
        'family.view' => ['View product family presentation and ordering defaults', 'View collections and ordering defaults'],
        'family.manage' => ['Create and manage catering and add-on collections', 'Create and manage primary and add-on collections'],
        'package.view' => ['View package drafts and configuration previews', 'View package drafts and price previews'],
        'package.manage' => ['Manage package products, variants and selection rules', 'Manage package products, variants and selection groups'],
        'package.pricing.manage' => ['Manage package base prices and option premiums', 'Manage draft prices and price adjustments'],
    ];

    public function up(): void
    {
        foreach (self::DESCRIPTIONS as $code => [$before, $after]) {
            DB::table('permissions')->where('code', $code)->where('description', $before)->update(['description' => $after]);
        }
    }

    public function down(): void
    {
        foreach (self::DESCRIPTIONS as $code => [$before, $after]) {
            DB::table('permissions')->where('code', $code)->where('description', $after)->update(['description' => $before]);
        }
    }
};
