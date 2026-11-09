<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Quotation\ApplyDirectQuotationAdjustment;
use App\Actions\Quotation\CreateManualQuotation;
use App\Actions\Quotation\CreateQuotationFromReservationRequest;
use App\Actions\Quotation\CreateQuotationRevision;
use App\Actions\Quotation\IssueQuotation;
use App\Actions\Quotation\RecordQuotationAcceptance;
use App\Actions\Quotation\RecordQuotationDelivery;
use App\Actions\Quotation\RequestQuotationAdjustment;
use App\Actions\Quotation\ReviewQuotationAdjustment;
use App\Actions\Quotation\WithdrawQuotation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Quotation\ApplyDirectQuotationAdjustmentRequest;
use App\Http\Requests\Quotation\CreateManualQuotationRequest;
use App\Http\Requests\Quotation\IssueQuotationRequest;
use App\Http\Requests\Quotation\RecordOfflineQuotationAcceptanceRequest;
use App\Http\Requests\Quotation\RecordQuotationDeliveryRequest;
use App\Http\Requests\Quotation\RequestQuotationAdjustmentRequest;
use App\Http\Requests\Quotation\ReviewQuotationAdjustmentRequest;
use App\Http\Requests\Quotation\WithdrawQuotationRequest;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\QuotationAdjustmentRequest;
use App\Models\QuotationVersion;
use App\Models\ReservationRequest;
use App\Services\Access\PermissionResolver;
use App\Services\Payments\PaymentBalance;
use App\Services\Quotation\QuotationDraftFactory;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class QuotationController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Quotation::class);
        $status = $request->string('status')->toString();
        $quotes = Quotation::query()->with(['currentVersion:id,quotation_id,version_number,status,total_minor,valid_until', 'reservationRequest:id,reference'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest('id')->paginate(30)->withQueryString();

        return Inertia::render('admin/quotations/index', ['quotations' => $quotes, 'status' => $status]);
    }

    public function show(Quotation $quotation, PaymentBalance $balances): Response
    {
        Gate::authorize('view', $quotation);
        $canViewPayments = request()->user()->can('viewAny', Payment::class);
        $quotation->load([
            'reservationRequest:id,reference,status', 'creator:id,name', 'currentVersion.termsVersion',
            'currentVersion.acceptance', 'currentVersion.adjustmentRequests.requester:id,name', 'currentVersion.adjustmentRequests.reviewer:id,name',
            'currentVersion.deliveryEvents.recorder:id,name',
            'versions.creator:id,name', 'versions.issuer:id,name', 'versions.acceptance',
            'versions.adjustmentRequests.requester:id,name', 'versions.adjustmentRequests.reviewer:id,name',
            'versions.deliveryEvents.recorder:id,name',
            ...($canViewPayments ? ['payments.method', 'payments.recorder:id,name', 'payments.verifier:id,name', 'payments.allocationHistory.quotationVersion', 'payments.evidenceVersions:id,payment_id,version_number,submitted_at'] : []),
        ]);

        return Inertia::render('admin/quotations/show', [
            'quotation' => $quotation,
            'canManage' => request()->user()->can('revise', $quotation),
            'canIssue' => $quotation->currentVersion && request()->user()->can('issue', $quotation->currentVersion),
            'canRequestAdjustment' => $quotation->currentVersion && request()->user()->can('requestAdjustment', $quotation->currentVersion),
            'canApplyDirectAdjustment' => $quotation->currentVersion && request()->user()->can('applyDirectAdjustment', $quotation->currentVersion),
            'canRecordDelivery' => $quotation->currentVersion && request()->user()->can('recordDelivery', $quotation->currentVersion),
            'canWithdraw' => $quotation->currentVersion && request()->user()->can('withdraw', $quotation->currentVersion),
            'canRecordOfflineAcceptance' => $quotation->currentVersion && request()->user()->can('recordOfflineAcceptance', $quotation->currentVersion),
            'canReviewAdjustment' => app(PermissionResolver::class)->allows(request()->user(), 'quotation.adjustment.approve'),
            'paymentBalance' => $canViewPayments ? $balances->forQuotation($quotation) : null,
            'payments' => $canViewPayments ? $quotation->payments : [],
            'canVerifyPayments' => request()->user()->isAdmin(),
            'canViewPaymentEvidence' => $canViewPayments,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Quotation::class);

        return Inertia::render('admin/quotations/create');
    }

    public function prepareFromRequest(ReservationRequest $reservationRequest, QuotationDraftFactory $drafts): Response|RedirectResponse
    {
        Gate::authorize('create', Quotation::class);
        Gate::authorize('view', $reservationRequest);
        $reservationRequest->load('lines');
        if ($reservationRequest->quotation()->exists()) {
            return redirect()->route('quotations.show', $reservationRequest->quotation()->sole());
        }
        $draft = $drafts->fromReservationRequest($reservationRequest);

        return Inertia::render('admin/quotations/prepare', [
            'request' => [
                'id' => $reservationRequest->id,
                'reference' => $reservationRequest->reference,
                'customer_name' => $reservationRequest->customer_name,
                'customer_mobile' => $reservationRequest->customer_mobile,
                'customer_email' => $reservationRequest->customer_email,
                'event_date' => $reservationRequest->event_date->toDateString(),
                'event_start_time' => $reservationRequest->event_start_time,
                'venue_information' => $reservationRequest->venue_information,
                'event_notes' => $reservationRequest->event_notes,
                'estimated_subtotal_minor' => $reservationRequest->estimated_subtotal_minor,
            ],
            'draft' => $draft,
        ]);
    }

    public function store(CreateManualQuotationRequest $request, CreateManualQuotation $action): RedirectResponse
    {
        $quotation = $action->handle($request->user(), $request->validated());

        return redirect()->route('quotations.show', $quotation)->with('status', 'Manual quotation draft created. It still requires approved terms before issuance.');
    }

    public function createFromRequest(Request $request, ReservationRequest $reservationRequest, CreateQuotationFromReservationRequest $action): RedirectResponse
    {
        Gate::authorize('create', Quotation::class);
        Gate::authorize('view', $reservationRequest);
        $quotation = $action->handle($request->user(), $reservationRequest);

        return redirect()->route('quotations.show', $quotation)->with('status', $quotation->getAttribute('existing_case')
            ? 'Opened the existing quotation case for this reservation request.'
            : 'Quotation draft saved from the immutable reservation-request snapshot.');
    }

    public function revise(Request $request, Quotation $quotation, CreateQuotationRevision $action): RedirectResponse
    {
        Gate::authorize('revise', $quotation);
        $version = $action->handle($request->user(), $quotation);

        return redirect()->route('quotations.show', $quotation)->with('status', "Draft revision v{$version->version_number} created. Issued history remains unchanged.");
    }

    public function issue(IssueQuotationRequest $request, QuotationVersion $quotationVersion, IssueQuotation $action): RedirectResponse
    {
        $value = $request->validated('valid_until');
        $issued = $action->handle($request->user(), $quotationVersion, is_string($value) ? CarbonImmutable::parse($value) : null);

        return redirect()->route('quotations.show', $quotationVersion->quotation_id)->with('status', 'Quotation issued. Customers can now review it through Track Your Request.');
    }

    public function withdraw(WithdrawQuotationRequest $request, QuotationVersion $quotationVersion, WithdrawQuotation $action): RedirectResponse
    {
        $action->handle($request->user(), $quotationVersion, $request->validated('reason'));

        return back()->with('status', 'Quotation withdrawn. Its historical commercial snapshot remains preserved.');
    }

    public function requestAdjustment(RequestQuotationAdjustmentRequest $request, QuotationVersion $quotationVersion, RequestQuotationAdjustment $action): RedirectResponse
    {
        $action->handle($request->user(), $quotationVersion, $request->validated('adjustment_amount'), $request->validated('direction'), $request->validated('reason'));

        return back()->with('status', 'Exceptional pricing request submitted for approval.');
    }

    public function applyDirectAdjustment(ApplyDirectQuotationAdjustmentRequest $request, QuotationVersion $quotationVersion, ApplyDirectQuotationAdjustment $action): RedirectResponse
    {
        $action->handle($request->user(), $quotationVersion, $request->validated('adjustment_amount'), $request->validated('direction'), $request->validated('reason'));

        return back()->with('status', 'Administrator adjustment applied to this draft and recorded in Activity History.');
    }

    public function reviewAdjustment(ReviewQuotationAdjustmentRequest $request, QuotationAdjustmentRequest $quotationAdjustmentRequest, ReviewQuotationAdjustment $action): RedirectResponse
    {
        $action->handle($request->user(), $quotationAdjustmentRequest, $request->boolean('approved'), $request->validated('review_note'));

        return back()->with('status', $request->boolean('approved') ? 'Exceptional pricing approved and applied to the draft.' : 'Exceptional pricing request declined.');
    }

    public function recordDelivery(RecordQuotationDeliveryRequest $request, QuotationVersion $quotationVersion, RecordQuotationDelivery $action): RedirectResponse
    {
        $action->handle($request->user(), $quotationVersion, $request->validated('channel'), $request->validated('destination_hint'), $request->validated('note'));

        return back()->with('status', 'Quotation delivery recorded. No message was sent automatically.');
    }

    public function recordOfflineAcceptance(RecordOfflineQuotationAcceptanceRequest $request, QuotationVersion $quotationVersion, RecordQuotationAcceptance $action): RedirectResponse
    {
        $path = $request->file('evidence')?->store("quotations/{$quotationVersion->id}/acceptance", 'local');
        try {
            $action->offline($quotationVersion, $request->user(), $request->validated('accepted_by_name'), $path ? 'local' : null, $path);
        } catch (\Throwable $exception) {
            // The evidence is not a commercial record until acceptance is
            // committed. Do not retain an orphaned private upload after a
            // stale-version or validation failure.
            if ($path !== null) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }

        return back()->with('status', 'Offline quotation acceptance recorded. Payment verification and reservation confirmation remain separate.');
    }
}
