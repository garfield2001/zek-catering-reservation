<?php

namespace App\Policies;

use App\Models\User;
use App\Services\Access\PermissionResolver;

class AuditEventPolicy
{
    public function viewAny(User $user): bool
    {
        return app(PermissionResolver::class)->allows($user, 'audit.view');
    }
}
