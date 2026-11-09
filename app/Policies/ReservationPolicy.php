<?php

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;
use App\Services\Access\PermissionResolver;

class ReservationPolicy
{
    public function __construct(private PermissionResolver $permissions) {}

    public function viewAny(User $user): bool
    {
        return $this->permissions->allows($user, 'reservation.view');
    }

    public function view(User $user, Reservation $reservation): bool
    {
        return $this->viewAny($user);
    }

    public function confirm(User $user): bool
    {
        return $user->active && $user->isAdmin();
    }

    public function manageCapacity(User $user): bool
    {
        return $user->active && $user->isAdmin();
    }

    public function complete(User $user, Reservation $reservation): bool
    {
        return $user->active && $user->isAdmin();
    }

    public function requestChange(User $user, Reservation $reservation): bool
    {
        return $this->permissions->allows($user, 'reservation.change.manage');
    }

    public function reviewChange(User $user, Reservation $reservation): bool
    {
        return $user->active && $user->isAdmin();
    }
}
