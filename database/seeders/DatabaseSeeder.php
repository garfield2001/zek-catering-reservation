<?php

namespace Database\Seeders;

use App\Models\FamilyOrderDefault;
use App\Models\Permission;
use App\Models\ProductFamily;
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
            ] as $code => $description) {
                Permission::firstOrCreate(['code' => $code], ['description' => $description]);
            }
            $staff->permissions()->syncWithoutDetaching([Permission::where('code', 'dashboard.view')->sole()->id]);

            foreach ([
                'main_catering' => ['Main Catering Packages', false, 150, true],
                'belly_lechon' => ['Belly Lechon Packages', true, 1, false],
                'food_pack' => ['Food Pack Lunch', true, 1, false],
                'food_tray' => ['Food Tray Menu', true, 1, false],
                'pica_pica' => ['Pica-Pica', true, 1, false],
            ] as $code => [$name, $isAddon, $minimum, $enforceFloor]) {
                $family = ProductFamily::firstOrCreate(['code' => $code], [
                    'name' => $name, 'is_addon' => $isAddon, 'active' => true,
                ]);
                FamilyOrderDefault::firstOrCreate(['family_id' => $family->id], [
                    'minimum_quantity' => $minimum, 'quantity_step' => 1,
                    'maximum_quantity' => null, 'enforce_minimum_floor' => $enforceFloor,
                ]);
            }
        });
    }
}
