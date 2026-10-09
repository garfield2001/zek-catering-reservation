<?php

namespace App\Http\Middleware;

use App\Services\Access\PermissionResolver;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return [...parent::share($request),
            'auth' => fn () => $request->user() ? [
                'user' => $request->user()->only('id', 'name', 'email'),
                'isAdmin' => $request->user()->isAdmin(),
                'permissions' => app(PermissionResolver::class)->codes($request->user()),
            ] : null,
            'flash' => ['status' => fn () => $request->session()->get('status')],
        ];
    }
}
