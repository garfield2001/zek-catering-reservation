<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Access\UpdatePermissions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Access\UpdatePermissionsRequest;
use App\Models\Permission;
use App\Models\User;
use App\Services\Access\PermissionResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PermissionController extends Controller
{
    public function edit(User $user, PermissionResolver $resolver): Response
    {
        Gate::authorize('permissions', $user);
        abort_if($user->isAdmin(), 422, 'Admin has full access.');

        return Inertia::render('admin/staff/permissions', [
            'staff' => $user->only('id', 'name', 'access_version'),
            'permissions' => Permission::orderBy('code')->get(['code', 'description']),
            'overrides' => $user->permissionOverrides->mapWithKeys(fn ($p) => [$p->code => $p->pivot->effect]),
            'roleGrants' => $user->roles->flatMap(fn ($r) => $r->permissions->pluck('code'))->unique()->values(),
            'grantable' => $resolver->codes(request()->user()),
        ]);
    }

    public function update(UpdatePermissionsRequest $request, User $user, UpdatePermissions $action): RedirectResponse
    {
        $action->handle($request->user(), $user, $request->validated());

        return redirect('/admin/staff')->with('status', 'Permissions updated. Existing sessions were revoked.');
    }
}
