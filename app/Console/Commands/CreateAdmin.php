<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Services\Access\AccessMutation;
use App\Services\Audit\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin';

    protected $description = 'Interactively bootstrap the first Admin; no credentials are seeded';

    public function handle(AccessMutation $access, AuditLogger $audit): int
    {
        $data = ['name' => $this->ask('Name'), 'email' => strtolower(trim((string) $this->ask('Email'))), 'password' => $this->secret('Password (12+ characters, mixed case and numbers)')];
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::min(12)->mixedCase()->numbers(), 'max:128'],
        ]);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        return DB::transaction(function () use ($access, $audit, $data) {
            $access->lock();
            if (User::whereHas('roles', fn ($q) => $q->where('code', 'admin'))->exists()) {
                $this->error('An Admin already exists. Use authenticated account administration.');

                return self::FAILURE;
            }
            $role = Role::where('code', 'admin')->sole();
            $user = User::create($data);
            $user->roles()->attach($role);
            $audit->record('access.admin_bootstrapped', null, $user, null, $access->snapshot($user));
            $this->info('Admin created.');

            return self::SUCCESS;
        });
    }
}
