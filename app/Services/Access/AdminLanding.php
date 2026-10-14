<?php

namespace App\Services\Access;

use App\Models\User;

class AdminLanding
{
    public function __construct(private PermissionResolver $permissions) {}

    public function path(User $user): ?string
    {
        foreach ([
            'dashboard.view' => '/admin',
            'family.view' => '/admin/add-on-services',
            'family.manage' => '/admin/add-on-services',
            'catalog.view' => '/admin/food-catalog',
            'catalog.manage' => '/admin/food-catalog',
            'package.view' => '/admin/catering-packages',
            'package.manage' => '/admin/catering-packages',
            'staff.view' => '/admin/staff',
            'audit.view' => '/admin/audit',
        ] as $permission => $path) {
            if ($this->permissions->allows($user, $permission)) {
                return $path;
            }
        }

        return null;
    }
}
