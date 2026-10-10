<?php

namespace App\Policies;

use App\Models\User;
use App\Services\Access\PermissionResolver;

class ProductFamilyPolicy
{
    public function __construct(private PermissionResolver $permissions) {}

    public function viewAny(User $user): bool
    {
        return $this->permissions->allows($user, 'family.view') || $this->permissions->allows($user, 'family.manage');
    }

    public function manage(User $user): bool
    {
        return $this->permissions->allows($user, 'family.manage');
    }
}
