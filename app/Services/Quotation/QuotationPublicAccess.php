<?php

namespace App\Services\Quotation;

use App\Enums\QuotationVersionStatus;
use App\Models\QuotationVerificationChallenge;
use App\Models\QuotationVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class QuotationPublicAccess
{
    private const SESSION_KEY = 'quotation.verified_access';

    public function findByToken(string $token): QuotationVersion
    {
        if (! preg_match('/^[a-f0-9]{64}$/D', $token)) {
            abort(404);
        }
        $version = QuotationVersion::query()->with('quotation')->where('public_token_hash', hash('sha256', $token))->firstOrFail();
        if (! in_array($version->status, [QuotationVersionStatus::Issued, QuotationVersionStatus::Accepted], true)
            || ($version->status === QuotationVersionStatus::Issued && $version->valid_until?->isPast())) {
            abort(410, 'This quotation link is no longer available.');
        }

        return $version;
    }

    /** @return array{challenge: QuotationVerificationChallenge, test_code: ?string} */
    public function sendChallenge(Request $request, QuotationVersion $version, bool $testDriver): array
    {
        if (! $testDriver || ! app()->environment(['local', 'testing'])) {
            throw ValidationException::withMessages(['verification' => 'SMS verification delivery is not configured for this environment. Use a clearly identified test driver only in local or testing environments.']);
        }
        $key = 'quotation-otp:send:'.$version->id.':'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 3)) {
            throw ValidationException::withMessages(['verification' => 'Please wait before requesting another verification code.']);
        }
        RateLimiter::hit($key, 15 * 60);
        $code = (string) random_int(100000, 999999);
        $challenge = QuotationVerificationChallenge::create([
            'quotation_version_id' => $version->id,
            'contact_hint' => $this->maskMobile($version->quotation->customer_mobile),
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(5),
        ]);

        return ['challenge' => $challenge, 'test_code' => $code];
    }

    public function verify(Request $request, QuotationVersion $version, string $code): QuotationVerificationChallenge
    {
        return DB::transaction(function () use ($request, $version, $code): QuotationVerificationChallenge {
            $challenge = QuotationVerificationChallenge::query()
                ->where('quotation_version_id', $version->id)
                ->whereNull('consumed_at')
                ->where('expires_at', '>', now())
                ->where('attempts', '<', 5)
                ->latest('id')
                ->lockForUpdate()
                ->first();
            if ($challenge === null) {
                throw ValidationException::withMessages(['code' => 'The verification code is invalid or expired. Request a new code.']);
            }
            if (! Hash::check($code, $challenge->code_hash)) {
                $challenge->increment('attempts');
                throw ValidationException::withMessages(['code' => 'The verification code is invalid or expired.']);
            }
            $verifiedUntil = now()->addMinutes(30);
            $challenge->update(['attempts' => $challenge->attempts + 1, 'consumed_at' => now(), 'verified_until' => $verifiedUntil]);
            $all = $request->session()->get(self::SESSION_KEY, []);
            $all[(string) $version->id] = ['challenge_id' => $challenge->id, 'expires_at' => $verifiedUntil->getTimestamp()];
            $request->session()->put(self::SESSION_KEY, $all);

            return $challenge->refresh();
        });
    }

    public function verifiedChallenge(Request $request, QuotationVersion $version): ?QuotationVerificationChallenge
    {
        $entry = $request->session()->get(self::SESSION_KEY.'.'.$version->id);
        if (! is_array($entry) || ! isset($entry['challenge_id'], $entry['expires_at']) || (int) $entry['expires_at'] < now()->getTimestamp()) {
            return null;
        }

        return QuotationVerificationChallenge::query()
            ->whereKey($entry['challenge_id'])
            ->where('quotation_version_id', $version->id)
            ->whereNotNull('consumed_at')
            ->where('verified_until', '>', now())
            ->first();
    }

    private function maskMobile(string $mobile): string
    {
        $last = substr($mobile, -4);

        return str_repeat('•', max(0, strlen($mobile) - 4)).$last;
    }
}
