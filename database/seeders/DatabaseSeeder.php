<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            Role::firstOrCreate(['code' => 'admin'], ['name' => 'Admin']);
            $staff = Role::firstOrCreate(['code' => 'staff'], ['name' => 'Staff']);
            foreach ([
                'dashboard.view' => 'View dashboard', 'staff.view' => 'View team members',
                'staff.manage' => 'Create and update team member accounts',
                'access.manage' => 'Assign predefined Staff permissions', 'audit.view' => 'View Activity History',
                'catalog.view' => 'View food items, categories and offerings',
                'catalog.manage' => 'Manage food items, categories, eligibility, offerings and serving options',
                'family.view' => 'View collections and ordering defaults',
                'family.manage' => 'Create and manage primary and add-on collections',
                'package.view' => 'View package drafts and price previews',
                'package.manage' => 'Manage package products, variants and selection groups',
                'package.pricing.manage' => 'Manage draft prices and price adjustments',
            ] as $code => $description) {
                Permission::firstOrCreate(['code' => $code], ['description' => $description]);
            }
            $staff->permissions()->syncWithoutDetaching([Permission::where('code', 'dashboard.view')->sole()->id]);

        });
    }
}
