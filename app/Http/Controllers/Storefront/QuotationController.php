<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quotation\SendQuotationOtpRequest;
use App\Http\Requests\Quotation\VerifyQuotationOtpRequest;
use App\Models\QuotationVersion;
use App\Services\Audit\AuditLogger;
use App\Services\Quotation\QuotationPublicAccess;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class QuotationController extends Controller
{
    public function show(string $token, QuotationPublicAccess $access): Response
    {
        $version = $access->findByToken($token);
        $challenge = $access->verifiedChallenge(request(), $version);
        $version->load(['quotation', 'acceptance']);

        return Inertia::render('storefront/quotations/show', [
            'token' => $token,
            'verified' => $challenge !== null,
            'contactHint' => $challenge?->contact_hint,
            'quotation' => $challenge ? $this->quotationData($version) : ['reference' => $version->quotation->reference, 'valid_until' => $version->valid_until?->toIso8601String()],
            'testDriverAvailable' => app()->environment(['local', 'testing']),
        ]);
    }

    public function send(SendQuotationOtpRequest $request, string $token, QuotationPublicAccess $access, AuditLogger $audit): RedirectResponse
    {
        $version = $access->findByToken($token);
        $result = $access->sendChallenge($request, $version, $request->boolean('test_driver'));
        $audit->record('quotation.otp_requested', null, $version, null, ['quotation_version_id' => $version->id, 'test_driver' => $request->boolean('test_driver')]);

        return back()->with('status', 'A verification code was prepared.')->with('quotation_test_otp', $result['test_code']);
    }

    public function verify(VerifyQuotationOtpRequest $request, string $token, QuotationPublicAccess $access, AuditLogger $audit): RedirectResponse
    {
        $version = $access->findByToken($token);
        $challenge = $access->verify($request, $version, $request->validated('code'));
        $audit->record('quotation.otp_verified', null, $version, null, ['quotation_version_id' => $version->id, 'challenge_id' => $challenge->id]);

        return back()->with('status', 'Your quote details are verified for 30 minutes.');
    }

    /** @return array<string, mixed> */
    private function quotationData(QuotationVersion $version): array
    {
        return [
            'reference' => $version->quotation->reference, 'status' => $version->status->value,
            'customer_name' => $version->quotation->customer_name, 'event_date' => $version->quotation->event_date?->toDateString(),
            'event_start_time' => $version->quotation->event_start_time, 'venue_information' => $version->quotation->venue_information,
            'line_snapshots' => $version->line_snapshots, 'subtotal_minor' => $version->subtotal_minor, 'adjustment_minor' => $version->adjustment_minor,
            'total_minor' => $version->total_minor, 'currency' => $version->currency, 'valid_until' => $version->valid_until?->toIso8601String(),
            'terms_snapshot' => $version->terms_snapshot, 'acceptance' => $version->acceptance,
        ];
    }
}
