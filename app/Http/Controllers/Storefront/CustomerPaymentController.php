<?php

namespace App\Http\Controllers\Storefront;

use App\Actions\Payments\ReplaceCustomerPaymentEvidence;
use App\Actions\Payments\SubmitCustomerPaymentEvidence;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\ReplaceCustomerPaymentEvidenceRequest;
use App\Http\Requests\Payments\StoreCustomerPaymentRequest;
use App\Models\Payment;
use App\Models\Quotation;
use App\Services\BusinessPolicies\CurrentBusinessPolicy;
use App\Services\Payments\CustomerPaymentUploadWindow;
use App\Services\ReservationRequest\CustomerProgressAccess;
use Illuminate\Http\RedirectResponse;

class CustomerPaymentController extends Controller
{
    public function store(StoreCustomerPaymentRequest $request, string $reference, CustomerProgressAccess $access, SubmitCustomerPaymentEvidence $payments): RedirectResponse
    {
        $quotation = Quotation::query()->with('reservationRequest')->whereHas('reservationRequest', fn ($query) => $query->where('reference', $reference))->firstOrFail();
        $access->require($request, $quotation->reservationRequest);
        $data = $request->validated();
        $payments->handle($quotation, $data, $request->file('proof'));

        return back()->with('status', 'Receipt submitted for Administrator verification. This submission records your acceptance of the displayed quotation; it does not yet verify payment or confirm a reservation.');
    }

    public function replace(ReplaceCustomerPaymentEvidenceRequest $request, string $reference, Payment $payment, CustomerProgressAccess $access, ReplaceCustomerPaymentEvidence $evidence, CurrentBusinessPolicy $policies, CustomerPaymentUploadWindow $window): RedirectResponse
    {
        $quotation = Quotation::query()->with('reservationRequest')->whereHas('reservationRequest', fn ($query) => $query->where('reference', $reference))->firstOrFail();
        $access->require($request, $quotation->reservationRequest);
        abort_unless($payment->quotation_id === $quotation->id && $payment->source === 'customer_submission', 404);
        $payment->loadMissing('submittedQuotationVersion');
        $cutoffDays = $payment->submittedQuotationVersion?->terms_snapshot['customer_payment_upload_cutoff_days'] ?? null;
        $window->assertOpen($quotation, $policies->current(), is_numeric($cutoffDays) ? (int) $cutoffDays : null);
        $evidence->handle($payment, $request->file('proof'));

        return back()->with('status', 'Receipt replaced and returned to Pending Verification. The prior image remains in protected history.');
    }
}
