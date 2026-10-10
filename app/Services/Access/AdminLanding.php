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
            'family.view' => '/admin/catalog/families',
            'family.manage' => '/admin/catalog/families',
            'catalog.view' => '/admin/catalog/dishes',
            'catalog.manage' => '/admin/catalog/dishes',
            'package.view' => '/admin/packages/products',
            'package.manage' => '/admin/packages/products',
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
