<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Access\SaveProfileAvatar;
use App\Http\Controllers\Controller;
use App\Http\Requests\Access\SaveProfileAvatarRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(): Response
    {
        $user = request()->user();

        return Inertia::render('admin/profile/edit', [
            'profile' => [...$user->only('id', 'name', 'email'), 'avatar_url' => $user->avatar_url],
        ]);
    }

    public function update(SaveProfileAvatarRequest $request, SaveProfileAvatar $action): RedirectResponse
    {
        $action->handle($request->user(), $request->validated());

        return back()->with('status', 'Profile photo updated.');
    }
}
