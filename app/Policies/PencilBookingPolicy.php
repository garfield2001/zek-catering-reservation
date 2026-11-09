<?php

namespace App\Policies;

use App\Models\PencilBooking;
use App\Models\User;
use App\Services\Access\PermissionResolver;

class PencilBookingPolicy
{
    public function __construct(private PermissionResolver $permissions) {}

    public function viewAny(User $user): bool
    {
        return $this->permissions->allows($user, 'pencil_booking.view') || $this->permissions->allows($user, 'pencil_booking.manage');
    }

    public function view(User $user, PencilBooking $booking): bool
    {
        return $this->viewAny($user);
    }

    public function manage(User $user): bool
    {
        return $this->permissions->allows($user, 'pencil_booking.manage');
    }
}
