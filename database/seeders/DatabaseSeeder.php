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
                'dashboard.view' => 'View the dashboard', 'staff.view' => 'View staff accounts',
                'staff.manage' => 'Create and update staff accounts',
                'access.manage' => 'Manage staff permission overrides', 'audit.view' => 'View audit history',
                'catalog.view' => 'View the master catalog',
                'catalog.manage' => 'Manage dishes, categories, eligibility, offerings and serving options',
                'family.view' => 'View product family presentation and ordering defaults',
                'family.manage' => 'Create and manage catering and add-on collections',
                'package.view' => 'View package drafts and configuration previews',
                'package.manage' => 'Manage package products, variants and selection rules',
                'package.pricing.manage' => 'Manage package base prices and option premiums',
            ] as $code => $description) {
                Permission::firstOrCreate(['code' => $code], ['description' => $description]);
            }
            $staff->permissions()->syncWithoutDetaching([Permission::where('code', 'dashboard.view')->sole()->id]);

        });
    }
}
