<?php

namespace App\Queries\Access;

use App\Models\User;
use App\Services\Access\AdministratorAuthority;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class TeamMemberDirectory
{
    public function __construct(private AdministratorAuthority $administrators) {}

    public function get(): LengthAwarePaginator
    {
        return User::with(['roles.permissions', 'permissionOverrides'])->orderBy('name')->paginate(20)->through(fn (User $user) => [
            ...$user->only('id', 'name', 'email', 'active', 'access_version'),
            'roles' => $user->roles->pluck('code'),
            'canUpdate' => Gate::allows('update', $user),
            'canManagePermissions' => ! $user->isAdmin() && Gate::allows('permissions', $user),
            'protectedAdministrator' => $this->administrators->isLastQualifying($user),
        ]);
    }
}
