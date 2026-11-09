<?php

namespace App\Policies;

use App\Models\ReservationRequest;
use App\Models\User;
use App\Services\Access\PermissionResolver;

class ReservationRequestPolicy
{
    public function __construct(private PermissionResolver $permissions) {}

    public function viewAny(User $user): bool
    {
        return $this->permissions->allows($user, 'reservation_request.view') || $this->permissions->allows($user, 'reservation_request.manage');
    }

    public function create(User $user): bool
    {
        return $this->permissions->allows($user, 'reservation_request.manage');
    }

    public function view(User $user, ReservationRequest $request): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, ReservationRequest $request): bool
    {
        return $this->permissions->allows($user, 'reservation_request.manage');
    }
}
