<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Services\Access\PermissionResolver;

class UserPolicy
{
    public function __construct(private PermissionResolver $permissions) {}

    public function viewAny(User $actor): bool
    {
        return $this->permissions->allows($actor, 'staff.view');
    }

    public function create(User $actor): bool
    {
        if (! $this->permissions->allows($actor, 'staff.manage')) {
            return false;
        }

        return Role::where('code', 'staff')->sole()->permissions
            ->every(fn ($permission) => $this->permissions->allows($actor, $permission->code));
    }

    public function update(User $actor, User $target): bool
    {
        return $this->permissions->allows($actor, 'staff.manage') && $this->permissions->canManage($actor, $target);
    }

    public function permissions(User $actor, User $target): bool
    {
        return $this->permissions->allows($actor, 'access.manage') && $this->permissions->canManage($actor, $target);
    }
}
