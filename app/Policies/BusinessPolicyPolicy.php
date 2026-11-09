<?php

namespace App\Policies;

use App\Models\BusinessPolicy;
use App\Models\User;
use App\Services\Access\PermissionResolver;

class BusinessPolicyPolicy
{
    public function __construct(private PermissionResolver $permissions) {}

    public function view(User $user, BusinessPolicy $policy): bool
    {
        return $user->active && ($user->isAdmin() || $this->permissions->allows($user, 'quotation.view') || $this->permissions->allows($user, 'quotation.manage'));
    }

    public function update(User $user, BusinessPolicy $policy): bool
    {
        return $user->active && $user->isAdmin();
    }
}
