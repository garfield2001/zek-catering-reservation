<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && (! $user->active || $request->session()->get('access_version') !== $user->access_version)) {
            Log::notice('Session revoked', ['user_id' => $user->id, 'request_id' => $request->attributes->get('request_id')]);
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/login')->with('status', 'Your access changed. Please sign in again.');
        }

        return $next($request);
    }
}
