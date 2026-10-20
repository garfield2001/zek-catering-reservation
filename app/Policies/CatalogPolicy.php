<?php

namespace App\Policies;

use App\Models\User;
use App\Services\Access\PermissionResolver;
use Illuminate\Database\Eloquent\Model;

class CatalogPolicy
{
    public function __construct(private PermissionResolver $permissions) {}

    public function viewAny(User $user): bool
    {
        return $this->permissions->allows($user, 'catalog.view')
            || $this->permissions->allows($user, 'catalog.manage');
    }

    public function view(User $user, Model $model): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->permissions->allows($user, 'catalog.manage');
    }

    public function update(User $user, Model $model): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->create($user);
    }
}
