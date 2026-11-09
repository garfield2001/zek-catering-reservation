<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Refund;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class RefundEvidenceController extends Controller
{
    public function download(Request $request, Refund $refund)
    {
        Gate::authorize('manageRefunds', $refund->cancellationRequest);
        abort_unless($refund->evidence_disk !== null && $refund->evidence_path !== null, 404);

        return Storage::disk($refund->evidence_disk)->download(
            $refund->evidence_path,
            "refund-evidence-{$refund->id}",
        );
    }

    public function preview(Request $request, Refund $refund)
    {
        Gate::authorize('manageRefunds', $refund->cancellationRequest);
        abort_unless($refund->evidence_disk !== null && $refund->evidence_path !== null, 404);

        return Storage::disk($refund->evidence_disk)->response(
            $refund->evidence_path,
            null,
            ['Content-Disposition' => "inline; filename=refund-evidence-{$refund->id}"],
        );
    }
}
