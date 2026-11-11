<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Payments\ReallocatePayment;
use App\Actions\Payments\RecordPayment;
use App\Actions\Payments\ReviewPayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\ReallocatePaymentRequest;
use App\Http\Requests\Payments\ReviewPaymentRequest;
use App\Http\Requests\Payments\StorePaymentRequest;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Quotation;
use App\Models\QuotationVersion;
use App\Services\Catalog\MinorMoney;
use App\Services\Payments\PaymentBalance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function index(Request $request, PaymentBalance $balances): Response
    {
        Gate::authorize('viewAny', Payment::class);
        $payments = Payment::query()->with(['method:id,name', 'quotation:id,reference', 'reservationRequest:id,reference', 'recorder:id,name', 'verifier:id,name', 'evidenceVersions:id,payment_id,version_number,submitted_at'])->latest('id')->paginate(30)->withQueryString();

        $payments->getCollection()->load(['allocationHistory.quotationVersion:id,quotation_id,version_number']);

        return Inertia::render('admin/payments/index', [
            'payments' => $payments,
            'methods' => PaymentMethod::query()
                ->where('is_active', true)
                ->orderBy('display_order')
                ->get(['id', 'name', 'is_offline']),
            'quotations' => Quotation::query()
                ->whereHas('versions', fn ($query) => $query->whereHas('acceptance'))
                ->latest('id')
                ->get(['id', 'reference']),
            'acceptedVersions' => QuotationVersion::query()
                ->whereHas('acceptance')
                ->with('quotation:id,reference')
                ->orderByDesc('id')
                ->get(['id', 'quotation_id', 'version_number']),
            'canRecord' => $request->user()->can('record', Payment::class),
            'canVerify' => $request->user()->isAdmin(),
            'canRecordEarlyException' => $request->user()->isAdmin(),
            'canViewQuotations' => $request->user()->can('viewAny', Quotation::class),
        ]);
    }

    public function store(StorePaymentRequest $request, RecordPayment $action, MinorMoney $money): RedirectResponse
    {
        $quotation = $request->validated('quotation_id') ? Quotation::query()->findOrFail($request->validated('quotation_id')) : null;
        $data = $request->validated();
        $data['amount_minor'] = $money->fromPesos($data['amount'], 'amount');
        $administrator = $request->user()->isAdmin();
        if ($quotation === null && ! $administrator) {
            abort(403);
        }
        if ($quotation === null && empty($data['exception_reason'])) {
            return back()->withErrors(['exception_reason' => 'An Administrator must record a reason for an early exceptional payment.']);
        }
        $source = $quotation === null ? 'administrator_exception' : 'staff_recorded';
        $payment = $action->handle($request->user(), $quotation, $data, $source, $request->file('proof'));
        if ($administrator && $quotation !== null) {
            app(ReviewPayment::class)->verify($request->user(), $payment);
        }

        return back()->with('status', $administrator && $quotation !== null ? 'Offline payment recorded, allocated and verified.' : 'Payment recorded for Administrator verification or later allocation.');
    }

    public function review(ReviewPaymentRequest $request, Payment $payment, ReviewPayment $action): RedirectResponse
    {
        $data = $request->validated();
        if ($data['action'] === 'verify') {
            $amount = app(MinorMoney::class)->fromPesos($data['verified_amount'], 'verified_amount');
            $action->verify($request->user(), $payment, isset($data['quotation_version_id']) ? QuotationVersion::query()->findOrFail($data['quotation_version_id']) : null, $amount, $data['customer_message'] ?? null);
        }
        if ($data['action'] === 'reject') {
            $action->reject($request->user(), $payment, $data['reason']);
        }
        if ($data['action'] === 'reverse') {
            $action->reverse($request->user(), $payment, $data['reason']);
        }

        return back()->with('status', 'Payment review recorded.');
    }

    public function reallocate(ReallocatePaymentRequest $request, Payment $payment, ReallocatePayment $action): RedirectResponse
    {
        $action->handle($request->user(), $payment, QuotationVersion::query()->findOrFail($request->validated('quotation_version_id')), $request->validated('reason'));

        return back()->with('status', 'Verified payment reallocated. Its prior allocation remains in financial history.');
    }
}
