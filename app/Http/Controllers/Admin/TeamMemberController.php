<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Access\SaveUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Access\SaveUserRequest;
use App\Models\User;
use App\Queries\Access\TeamMemberDirectory;
use App\Services\Access\AdministratorAuthority;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TeamMemberController extends Controller
{
    public function index(Request $request, TeamMemberDirectory $directory): Response
    {
        Gate::authorize('viewAny', User::class);

        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:active,inactive'], 'sort' => ['nullable', 'in:name,email,created_at'], 'direction' => ['nullable', 'in:asc,desc']]);

        return Inertia::render('admin/staff/index', ['filters' => $filters, 'users' => $directory->get($filters), 'canCreate' => Gate::allows('create', User::class)]);
    }

    public function create(): Response
    {
        Gate::authorize('create', User::class);

        return Inertia::render('admin/staff/edit', ['staff' => null]);
    }

    public function store(SaveUserRequest $request, SaveUser $action): RedirectResponse
    {
        $action->handle($request->user(), $request->validated());

        return redirect('/admin/staff')->with('status', 'Account created.');
    }

    public function edit(User $user, AdministratorAuthority $administrators): Response
    {
        Gate::authorize('update', $user);

        return Inertia::render('admin/staff/edit', ['staff' => [...$user->only('id', 'name', 'email', 'active', 'access_version'),
            'role' => $user->isAdmin() ? 'admin' : 'staff',
            'protectedAdministrator' => $administrators->isLastQualifying($user)]]);
    }

    public function update(SaveUserRequest $request, User $user, SaveUser $action): RedirectResponse
    {
        $action->handle($request->user(), $request->validated(), $user);

        return redirect('/admin/home')->with('status', 'Account updated. Existing sessions were revoked.');
    }
}
