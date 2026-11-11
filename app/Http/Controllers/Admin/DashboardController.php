<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dish;
use App\Models\DishCategory;
use App\Models\Payment;
use App\Models\PencilBooking;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\ReservationRequest;
use App\Queries\Dashboard\OperationsDashboard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, OperationsDashboard $operations): Response
    {
        $user = $request->user();

        return Inertia::render('admin/dashboard', [
            'setup' => [
                'categories' => Gate::allows('viewAny', Dish::class) ? DishCategory::count() : null,
                'items' => Gate::allows('viewAny', Dish::class) ? Dish::count() : null,
                'products' => Gate::allows('viewAny', Product::class) ? Product::count() : null,
            ],
            'operations' => $operations->for($user),
            'quickActions' => array_values(array_filter([
                $user->can('create', ReservationRequest::class) ? [
                    'label' => 'Create internal request',
                    'description' => 'Start a standard request using current published offerings.',
                    'href' => '/admin/reservation-requests/create',
                ] : null,
                $user->can('create', Quotation::class) ? [
                    'label' => 'Create manual quotation',
                    'description' => 'Create a Draft; issuance remains a separate action.',
                    'href' => '/admin/quotations/create',
                ] : null,
                $user->can('record', Payment::class) ? [
                    'label' => 'Record payment',
                    'description' => 'Record an offline receipt for later review.',
                    'href' => '/admin/payments',
                ] : null,
                $user->can('manage', PencilBooking::class) ? [
                    'label' => 'Create pencil booking',
                    'description' => 'Record a non-binding prospect and follow-up.',
                    'href' => '/admin/pencil-bookings',
                ] : null,
            ])),
        ]);
    }
}
