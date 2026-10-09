<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    public function __invoke(): Response
    {
        Gate::authorize('viewAny', AuditEvent::class);

        return Inertia::render('admin/audit/index', ['events' => AuditEvent::orderByDesc('id')->paginate(30)]);
    }
}
