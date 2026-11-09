<?php

namespace App\Http\Controllers\Admin;

use App\Actions\BusinessPolicies\SaveBusinessPolicy;
use App\Http\Controllers\Controller;
use App\Http\Requests\BusinessPolicies\SaveBusinessPolicyRequest;
use App\Services\BusinessPolicies\CurrentBusinessPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BusinessPolicyController extends Controller
{
    public function show(CurrentBusinessPolicy $current): Response
    {
        $policy = $current->current();
        Gate::authorize('view', $policy);

        return Inertia::render('admin/business-policies/show', [
            'policy' => [
                ...$policy->only([
                    'minimum_advance_booking_days', 'minimum_rescheduling_notice_days', 'event_duration_hours',
                    'cancellation_notice_days', 'cancellation_refund_guidance',
                    'daily_confirmed_event_capacity', 'daily_guest_capacity',
                    'emergency_booking_requires_administrator', 'quotation_validity_days',
                    'allow_quotation_validity_overrides', 'reservation_payment_type',
                    'reservation_payment_basis_points', 'reservation_payment_deadline_hours',
                    'final_payment_days_before_event', 'menu_finalization_days_after_acceptance', 'updated_at',
                    'customer_payment_upload_cutoff_days', 'payment_account_name', 'payment_ewallet_number',
                    'payment_bank_account', 'payment_additional_instructions',
                ]),
                'reservation_payment_amount' => $policy->reservationPaymentPesos(),
            ],
            'canManage' => request()->user()->can('update', $policy),
        ]);
    }

    public function update(SaveBusinessPolicyRequest $request, SaveBusinessPolicy $action): RedirectResponse
    {
        $action->handle($request->user(), $request->validated());

        return back()->with('status', 'Business Policies saved. Newly issued quotations will keep a snapshot of these values.');
    }
}
