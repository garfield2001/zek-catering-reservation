<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Local-only convenience account. DatabaseSeeder controls the environment
 * boundary; this class must never be invoked from production provisioning.
 */
class LocalDevelopmentAdministratorSeeder extends Seeder
{
    public const EMAIL = 'admin@gmail.com';

    public const PASSWORD = 'adminpass123';

    public function run(): void
    {
        DB::transaction(function (): void {
            $adminRole = Role::query()->where('code', 'admin')->lockForUpdate()->firstOrFail();
            $existing = User::query()->where('email', self::EMAIL)->lockForUpdate()->first();

            if ($existing) {
                if (! $existing->active || ! $existing->roles()->where('roles.id', $adminRole->id)->exists()) {
                    throw new \RuntimeException('The local development Administrator email belongs to a different account. Resolve the conflict explicitly; the seeder will not change its access or password.');
                }

                return;
            }

            $user = User::create([
                'name' => 'Admin Tester',
                'email' => self::EMAIL,
                'password' => Hash::make(self::PASSWORD),
                'active' => true,
            ]);
            $user->roles()->attach($adminRole);
        });
    }
}
