<?php

namespace Database\Seeders;

use App\Actions\Catalog\EnsurePrimaryService;
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
            foreach (
                [
                    'dashboard.view' => 'View dashboard',
                    'staff.view' => 'View team members',
                    'staff.manage' => 'Create and update team member accounts',
                    'access.manage' => 'Assign predefined Staff permissions',
                    'audit.view' => 'View Activity History',
                    'catalog.view' => 'View food items, categories and offerings',
                    'catalog.manage' => 'Manage food items, categories, eligibility, offerings and serving options',
                    'family.view' => 'View collections and ordering defaults',
                    'family.manage' => 'Create and manage primary and add-on collections',
                    'package.view' => 'View package drafts and price previews',
                    'package.manage' => 'Manage package products and configuration rules',
                    'package.pricing.manage' => 'Manage draft prices and price adjustments',
                    'package.publish' => 'Publish package versions',
                    'package.history.view' => 'View published and abandoned package history',
                    'package.lifecycle.manage' => 'Disable and archive package offerings',
                    'reservation_request.view' => 'View reservation requests',
                    'reservation_request.manage' => 'Review and update reservation request statuses',
                    'reservation.view' => 'View reservation confirmation and availability information',
                    'reservation.change.manage' => 'Prepare and submit reservation change requests for review',
                    'cancellation.manage' => 'Prepare and view cancellation and refund requests',
                    'pencil_booking.view' => 'View pencil bookings',
                    'pencil_booking.manage' => 'Create, update, close and convert pencil bookings',
                    'quotation.view' => 'View quotations',
                    'quotation.manage' => 'Create and manage quotation drafts',
                    'quotation.issue' => 'Issue standard quotations',
                    'quotation.adjustment.request' => 'Request exceptional quotation pricing',
                    'quotation.adjustment.approve' => 'Approve exceptional quotation pricing',
                    'quotation.acceptance.record' => 'Record offline quotation acceptance',
                    'quotation.delivery.record' => 'Record quotation delivery',
                    'payment.view' => 'View payment records and balances',
                    'payment.record' => 'Record received payments for Administrator verification',
                    'payment.verify' => 'Verify, reject and reverse payments',
                ] as $code => $description
            ) {
                Permission::firstOrCreate(['code' => $code], ['description' => $description]);
            }
            $staff->permissions()->syncWithoutDetaching([Permission::where('code', 'dashboard.view')->sole()->id]);

            app(EnsurePrimaryService::class)->handle();
        });

        $this->call(BusinessPolicyDefaultsSeeder::class);

        if ($this->shouldSeedLocalDevelopmentData()) {
            $this->call([
                LocalDevelopmentAdministratorSeeder::class,
                DevelopmentDemoSeeder::class,
            ]);
        }
    }

    private function shouldSeedLocalDevelopmentData(): bool
    {
        return app()->environment('local')
            || (app()->environment('testing') && config('app.seed_local_development_data', false));
    }
}
