<?php

namespace App\Services\ReservationRequest;

use App\Models\ReservationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class CustomerProgressAccess
{
    private const SESSION_KEY = 'customer_request_access';

    public function grant(Request $http, string $reference, string $mobile, string $code): ReservationRequest
    {
        $key = 'customer-progress:'.sha1($http->ip().'|'.strtoupper(trim($reference)));
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['access' => 'We could not verify those details. Please wait before trying again.']);
        }
        RateLimiter::hit($key, 15 * 60);
        $request = ReservationRequest::query()->where('reference', strtoupper(trim($reference)))->first();
        $valid = $request !== null
            && hash_equals(trim($request->customer_mobile), trim($mobile))
            && $request->customer_access_code_hash !== null
            && Hash::check(strtoupper(trim($code)), $request->customer_access_code_hash);
        if (! $valid) {
            throw ValidationException::withMessages(['access' => 'We could not verify those details. Check your reference, mobile number, and access code.']);
        }
        $http->session()->put(self::SESSION_KEY.'.'.$request->id, now()->addMinutes(30)->getTimestamp());
        RateLimiter::clear($key);

        return $request;
    }

    public function authorized(Request $http, ReservationRequest $request): bool
    {
        return (int) $http->session()->get(self::SESSION_KEY.'.'.$request->id, 0) >= now()->getTimestamp();
    }

    public function require(Request $http, ReservationRequest $request): void
    {
        if (! $this->authorized($http, $request)) {
            throw ValidationException::withMessages(['access' => 'Enter your request reference, mobile number, and access code to continue.']);
        }
    }
}
