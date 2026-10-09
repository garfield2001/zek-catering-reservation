<?php

namespace App\Services\Access;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccessMutation
{
    public function lock(): void
    {
        // Serialize low-volume access administration and bootstrap, including last-Admin decisions.
        Role::orderBy('id')->lockForUpdate()->get();
    }

    public function revoke(User $user): void
    {
        $user->access_version++;
        $user->remember_token = Str::random(60);
        $user->save();
        DB::table('sessions')->where('user_id', $user->id)->delete();
    }

    public function snapshot(User $user): array
    {
        return [...$user->only('name', 'email', 'active', 'access_version'),
            'roles' => $user->roles()->pluck('code')->all(),
            'overrides' => $user->permissionOverrides()->get()->mapWithKeys(fn ($p) => [$p->code => $p->pivot->effect])->all(),
        ];
    }
}
