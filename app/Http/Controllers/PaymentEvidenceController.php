<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\PaymentEvidenceVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class PaymentEvidenceController extends Controller
{
    public function download(Request $request, Payment $payment)
    {
        Gate::authorize('view', $payment);
        abort_unless($payment->proof_disk !== null && $payment->proof_path !== null, 404);

        return Storage::disk($payment->proof_disk)->download($payment->proof_path, 'payment-proof-'.$payment->id);
    }

    public function preview(Request $request, Payment $payment)
    {
        Gate::authorize('view', $payment);
        abort_unless($payment->proof_disk !== null && $payment->proof_path !== null, 404);

        return Storage::disk($payment->proof_disk)->response($payment->proof_path, null, ['Content-Disposition' => 'inline; filename="payment-proof-'.$payment->id.'"']);
    }

    public function downloadVersion(Request $request, Payment $payment, PaymentEvidenceVersion $evidenceVersion)
    {
        Gate::authorize('view', $payment);
        abort_unless($evidenceVersion->payment_id === $payment->id, 404);

        return Storage::disk($evidenceVersion->disk)->download(
            $evidenceVersion->path,
            "payment-proof-{$payment->id}-v{$evidenceVersion->version_number}",
        );
    }

    public function previewVersion(Request $request, Payment $payment, PaymentEvidenceVersion $evidenceVersion)
    {
        Gate::authorize('view', $payment);
        abort_unless($evidenceVersion->payment_id === $payment->id, 404);

        return Storage::disk($evidenceVersion->disk)->response(
            $evidenceVersion->path,
            null,
            ['Content-Disposition' => "inline; filename=payment-proof-{$payment->id}-v{$evidenceVersion->version_number}"],
        );
    }
}
