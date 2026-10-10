<?php

namespace App\Services\Access;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class AdministratorAuthority
{
    private ?int $qualifyingCount = null;

    public function isLastQualifying(User $user): bool
    {
        return $user->active && $user->isAdmin() && $this->qualifyingCount() === 1;
    }

    public function assertCanRemove(User $user): void
    {
        if ($this->isLastQualifying($user)) {
            throw ValidationException::withMessages([
                'active' => 'At least one active Administrator with full account and permission access must remain.',
            ]);
        }
    }

    private function qualifyingCount(): int
    {
        // The Admin role has full authority in PermissionResolver; overrides cannot reduce it.
        return $this->qualifyingCount ??= User::query()->where('active', true)
            ->whereHas('roles', fn ($query) => $query->where('code', 'admin'))->count();
    }
}
