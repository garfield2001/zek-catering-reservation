<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;
use App\Services\Access\PermissionResolver;

class PaymentPolicy
{
    public function __construct(private PermissionResolver $permissions) {}

    public function viewAny(User $user): bool
    {
        return $this->permissions->allows($user, 'payment.view') || $this->permissions->allows($user, 'payment.record');
    }

    public function view(User $user, Payment $payment): bool
    {
        return $this->viewAny($user);
    }

    public function record(User $user): bool
    {
        return $this->permissions->allows($user, 'payment.record');
    }

    public function verify(User $user, Payment $payment): bool
    {
        return $user->active && $user->isAdmin();
    }

    public function manageMethods(User $user): bool
    {
        return $user->active && $user->isAdmin();
    }
}
