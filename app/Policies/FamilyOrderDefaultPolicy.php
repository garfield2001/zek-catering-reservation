<?php

namespace App\Policies;

use App\Models\FamilyOrderDefault;
use App\Models\User;

class FamilyOrderDefaultPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->active && $user->isAdmin();
    }

    public function update(User $user, FamilyOrderDefault $default): bool
    {
        return $this->viewAny($user);
    }
}
