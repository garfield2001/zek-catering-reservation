<?php

namespace App\Policies;

use App\Models\User;
use App\Services\Access\PermissionResolver;
use Illuminate\Database\Eloquent\Model;

class ListingPolicy
{
    public function __construct(private PermissionResolver $permissions) {}

    public function viewAny(User $user): bool
    {
        return $this->permissions->allows($user, 'listing.view') || $this->permissions->allows($user, 'listing.manage');
    }

    public function view(User $user, Model $model): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->permissions->allows($user, 'listing.manage');
    }

    public function update(User $user, Model $model): bool
    {
        return $this->create($user);
    }

    public function price(User $user): bool
    {
        return $this->permissions->allows($user, 'listing.pricing.manage');
    }

    public function publish(User $user): bool
    {
        return $this->permissions->allows($user, 'listing.publish');
    }

    public function history(User $user): bool
    {
        return $this->permissions->allows($user, 'listing.history.view');
    }

    public function lifecycle(User $user): bool
    {
        return $this->permissions->allows($user, 'listing.lifecycle.manage');
    }
}
