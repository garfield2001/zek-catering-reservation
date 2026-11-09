<?php

namespace App\Policies;

use App\Models\CancellationRequest;
use App\Models\User;
use App\Services\Access\PermissionResolver;

class CancellationRequestPolicy
{
    public function __construct(private PermissionResolver $permissions) {}

    public function viewAny(User $user): bool
    {
        return $this->permissions->allows($user, 'cancellation.manage');
    }

    public function view(User $user, CancellationRequest $cancellation): bool
    {
        return $this->viewAny($user);
    }

    public function request(User $user): bool
    {
        return $this->permissions->allows($user, 'cancellation.manage');
    }

    public function review(User $user, CancellationRequest $cancellation): bool
    {
        return $user->active && $user->isAdmin();
    }

    public function manageRefunds(User $user, CancellationRequest $cancellation): bool
    {
        return $user->active && $user->isAdmin();
    }
}
