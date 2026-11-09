<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\SavePaymentMethodRequest;
use App\Models\PaymentMethod;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PaymentMethodController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('manageMethods', PaymentMethod::class);

        return Inertia::render('admin/payments/methods', ['methods' => PaymentMethod::query()->orderBy('display_order')->get()]);
    }

    public function store(SavePaymentMethodRequest $request, AuditLogger $audit): RedirectResponse
    {
        $method = PaymentMethod::create([...$request->validated(), 'code' => 'payment_'.Str::lower(Str::random(12)), 'updated_by' => $request->user()->id]);
        $audit->record('payment_method.created', $request->user(), $method, null, ['code' => $method->code, 'name' => $method->name]);

        return back();
    }

    public function update(SavePaymentMethodRequest $request, PaymentMethod $paymentMethod, AuditLogger $audit): RedirectResponse
    {
        $fields = ['name', 'instructions', 'destination_information', 'is_active', 'is_offline', 'display_order'];
        $before = $paymentMethod->only($fields);
        $paymentMethod->update([...$request->validated(), 'updated_by' => $request->user()->id]);
        $audit->record('payment_method.updated', $request->user(), $paymentMethod, $before, $paymentMethod->only($fields));

        return back();
    }
}
