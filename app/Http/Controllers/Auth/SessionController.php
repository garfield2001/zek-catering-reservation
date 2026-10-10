<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\Login;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Access\PermissionResolver;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/login');
    }

    public function store(LoginRequest $request, Login $login, PermissionResolver $permissions): RedirectResponse
    {
        $login->handle($request);

        $intended = $request->session()->get('url.intended');
        if (is_string($intended) && parse_url($intended, PHP_URL_PATH) === '/admin'
            && ! $permissions->allows($request->user(), 'dashboard.view')) {
            $request->session()->forget('url.intended');

            return redirect('/admin/home');
        }

        return redirect()->intended('/admin/home');
    }

    public function destroy(Request $request, AuditLogger $audit): RedirectResponse
    {
        $audit->record('auth.logout', $request->user(), $request->user());
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
