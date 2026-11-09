<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Cancellations\CompleteRefund;
use App\Actions\Cancellations\CorrectCompletedRefund;
use App\Actions\Cancellations\CreateCancellationRequest;
use App\Actions\Cancellations\CreateRefund;
use App\Actions\Cancellations\ReviewCancellationRequest;
use App\Actions\Cancellations\ReviewRefund;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cancellations\CompleteRefundRequest;
use App\Http\Requests\Cancellations\CorrectRefundRequest;
use App\Http\Requests\Cancellations\ReviewCancellationRequest as ReviewCancellationRequestForm;
use App\Http\Requests\Cancellations\ReviewRefundRequest;
use App\Http\Requests\Cancellations\StoreCancellationRequest;
use App\Http\Requests\Cancellations\StoreRefundRequest;
use App\Models\CancellationRequest;
use App\Models\Quotation;
use App\Models\Refund;
use App\Services\Catalog\MinorMoney;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CancellationController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', CancellationRequest::class);
        $cancellations = CancellationRequest::query()
            ->with(['quotation:id,reference,customer_name,event_date', 'reservation:id,reference,status', 'requester:id,name', 'reviewer:id,name', 'refunds'])
            ->latest('id')->paginate(30)->withQueryString();

        return Inertia::render('admin/cancellations/index', [
            'cancellations' => $cancellations,
            'canReview' => $request->user()->isAdmin(),
        ]);
    }

    public function store(StoreCancellationRequest $request, Quotation $quotation, CreateCancellationRequest $action): RedirectResponse
    {
        Gate::authorize('request', CancellationRequest::class);
        $cancellation = $action->handle($request->user(), $quotation, $request->validated(), false);

        return redirect()->route('reservations.show', $quotation)->with('status', "Cancellation request #{$cancellation->id} is awaiting Administrator review.");
    }

    public function direct(StoreCancellationRequest $request, Quotation $quotation, CreateCancellationRequest $create, ReviewCancellationRequest $review): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $cancellation = $create->handle($request->user(), $quotation, $request->validated(), false, true);
        $review->handle($request->user(), $cancellation, true, $request->validated('reason'));

        return redirect()->route('reservations.show', $quotation)->with('status', 'Business-initiated cancellation was finalized. Capacity is released only because finalization succeeded.');
    }

    public function review(ReviewCancellationRequestForm $request, CancellationRequest $cancellationRequest, ReviewCancellationRequest $action): RedirectResponse
    {
        Gate::authorize('review', $cancellationRequest);
        $result = $action->handle($request->user(), $cancellationRequest, $request->boolean('approved'), $request->validated('review_note'));

        return redirect()->route('reservations.show', $result->quotation_id)->with('status', $result->status->value === 'declined' ? 'Cancellation request declined. The existing case remains unchanged.' : 'Cancellation finalized. Payment and refund history remains preserved for review.');
    }

    public function storeRefund(StoreRefundRequest $request, CancellationRequest $cancellationRequest, CreateRefund $action, MinorMoney $money): RedirectResponse
    {
        Gate::authorize('manageRefunds', $cancellationRequest);
        $data = $request->validated();
        $data['amount_minor'] = $money->fromPesos($data['amount_pesos'], 'amount_pesos');
        $refund = $action->handle($request->user(), $cancellationRequest, $data);

        return back()->with('status', "Refund #{$refund->id} is awaiting Administrator approval. It has not been sent.");
    }

    public function reviewRefund(ReviewRefundRequest $request, Refund $refund, ReviewRefund $action): RedirectResponse
    {
        Gate::authorize('manageRefunds', $refund->cancellationRequest);
        $result = $action->handle($request->user(), $refund, $request->boolean('approved'), (string) $request->validated('review_note'));

        return back()->with('status', $result->status->value === 'approved' ? 'Refund approved. Record completion only after the money is actually returned.' : 'Refund request rejected.');
    }

    public function completeRefund(CompleteRefundRequest $request, Refund $refund, CompleteRefund $action): RedirectResponse
    {
        Gate::authorize('manageRefunds', $refund->cancellationRequest);
        $data = $request->validated();
        if ($request->hasFile('evidence')) {
            $file = $request->file('evidence');
            $path = $file->storeAs('refund-evidence', Str::uuid().'.'.$file->extension(), 'local');
            $data += ['evidence_disk' => 'local', 'evidence_path' => $path, 'evidence_sha256' => hash_file('sha256', $file->getRealPath())];
        }
        $action->handle($request->user(), $refund, $data);

        return back()->with('status', 'Refund completion recorded. This confirms the money was returned outside the application.');
    }

    public function correctRefund(CorrectRefundRequest $request, Refund $refund, CorrectCompletedRefund $action, MinorMoney $money): RedirectResponse
    {
        Gate::authorize('manageRefunds', $refund->cancellationRequest);
        $input = $request->validated('correction_pesos');
        $negative = str_starts_with($input, '-');
        $minor = $money->fromPesos(ltrim($input, '-'), 'correction_pesos');
        $action->handle($request->user(), $refund, $negative ? -$minor : $minor, $request->validated('reason'));

        return back()->with('status', 'An append-only refund correction was recorded. The original completion remains in history.');
    }
}
