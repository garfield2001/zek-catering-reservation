<?php

namespace App\Services\Access;

use App\Models\Permission;
use App\Models\User;

class PermissionResolver
{
    private ?array $permissionCodes = null;

    public function allows(User $user, string $code): bool
    {
        if (! $user->active) {
            return false;
        }
        $user->loadMissing(['roles.permissions', 'permissionOverrides']);
        if ($user->isAdmin()) {
            return true;
        }
        $override = $user->permissionOverrides->firstWhere('code', $code);
        if ($override) {
            return $override->pivot->effect === 'allow';
        }

        return $user->roles->contains(fn ($role) => $role->permissions->contains('code', $code));
    }

    public function codes(User $user): array
    {
        $this->permissionCodes ??= Permission::query()->pluck('code')->all();

        return array_values(array_filter($this->permissionCodes, fn ($code) => $this->allows($user, $code)));
    }

    public function canManage(User $actor, User $target): bool
    {
        if (! $actor->active) {
            return false;
        }
        if ($actor->isAdmin()) {
            return true;
        }
        $effectiveTarget = clone $target;
        $effectiveTarget->active = true;

        return ! $target->isAdmin() && $actor->id !== $target->id
            && count(array_diff($this->codes($effectiveTarget), $this->codes($actor))) === 0;
    }
}
