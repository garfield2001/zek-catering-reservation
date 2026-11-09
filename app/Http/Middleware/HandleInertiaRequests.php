<?php

namespace App\Http\Middleware;

use App\Services\Access\PermissionResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return [...parent::share($request),
            'auth' => fn () => $request->user() ? [
                'user' => [...$request->user()->only('id', 'name', 'email'), 'avatar_url' => $request->user()->avatar_url],
                'isAdmin' => $request->user()->isAdmin(),
                'permissions' => app(PermissionResolver::class)->codes($request->user()),
            ] : null,
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'id' => fn () => $request->session()->has('status') ? (string) Str::uuid() : null,
                // A reset code is delivered exactly once in the authorized
                // response that generated it; the database retains only its
                // hash and audit metadata.
                'customer_access_code' => fn () => $request->session()->get('customer_access_code'),
                // Customer access is through Track Your Request and its
                // inquiry-specific access code. Legacy token/OTP records are
                // preserved for historical quotations but are not shared.
                'quotation_test_otp' => fn () => app()->environment(['local', 'testing']) ? $request->session()->get('quotation_test_otp') : null,
            ],
        ];
    }
}
