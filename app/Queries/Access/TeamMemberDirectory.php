<?php

namespace App\Queries\Access;

use App\Models\User;
use App\Services\Access\AdministratorAuthority;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class TeamMemberDirectory
{
    public function __construct(private AdministratorAuthority $administrators) {}

    public function get(array $filters = []): LengthAwarePaginator
    {
        return User::with(['roles.permissions', 'permissionOverrides'])
            ->when($filters['q'] ?? null, fn ($query, $term) => $query->where(fn ($search) => $search->where('name', 'ilike', '%'.addcslashes($term, '%_\\\\').'%')->orWhere('email', 'ilike', '%'.addcslashes($term, '%_\\\\').'%')))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('active', $status === 'active'))
            ->orderBy($filters['sort'] ?? 'name', $filters['direction'] ?? 'asc')->orderBy('id')->paginate(20)->withQueryString()->through(fn (User $user) => [
                ...$user->only('id', 'name', 'email', 'active', 'access_version'),
                'avatar_url' => $user->avatar_url,
                'roles' => $user->roles->pluck('code'),
                'canUpdate' => Gate::allows('update', $user),
                'canManagePermissions' => ! $user->isAdmin() && Gate::allows('permissions', $user),
                'protectedAdministrator' => $this->administrators->isLastQualifying($user),
            ]);
    }
}
