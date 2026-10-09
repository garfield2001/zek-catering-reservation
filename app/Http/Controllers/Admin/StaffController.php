<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Access\SaveUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Access\SaveUserRequest;
use App\Models\User;
use App\Queries\Access\StaffDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class StaffController extends Controller
{
    public function index(StaffDirectory $directory): Response
    {
        Gate::authorize('viewAny', User::class);

        return Inertia::render('admin/staff/index', ['users' => $directory->get(), 'canCreate' => Gate::allows('create', User::class)]);
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

    public function edit(User $user): Response
    {
        Gate::authorize('update', $user);

        return Inertia::render('admin/staff/edit', ['staff' => [...$user->only('id', 'name', 'email', 'active', 'access_version'), 'role' => $user->isAdmin() ? 'admin' : 'staff']]);
    }

    public function update(SaveUserRequest $request, User $user, SaveUser $action): RedirectResponse
    {
        $action->handle($request->user(), $request->validated(), $user);

        return redirect('/admin/staff')->with('status', 'Account updated. Existing sessions were revoked.');
    }
}
