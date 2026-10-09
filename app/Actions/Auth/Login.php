<?php

namespace App\Actions\Auth;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class Login
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(LoginRequest $request): void
    {
        $key = 'login:'.hash('sha256', $request->validated('email').'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.']);
        }
        $user = DB::transaction(function () use ($request) {
            $user = User::where('email', $request->validated('email'))->lockForUpdate()->first();
            // A dummy hash check keeps unknown-user responses similar to wrong passwords.
            $valid = Hash::check($request->validated('password'), $user->password ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
            if (! $valid || ! $user?->active) {
                return null;
            }
            if (Hash::needsRehash($user->password)) {
                $user->update(['password' => $request->validated('password')]);
            }
            $this->audit->record('auth.login', $user, $user);

            return $user;
        });
        if (! $user) {
            RateLimiter::hit($key, 60);
            Log::warning('Authentication rejected', ['request_id' => $request->attributes->get('request_id'), 'ip' => $request->ip()]);
            throw ValidationException::withMessages(['email' => 'These credentials do not match an active account.']);
        }
        RateLimiter::clear($key);
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('access_version', $user->access_version);
    }
}
