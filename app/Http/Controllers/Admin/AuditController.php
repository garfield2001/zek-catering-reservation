<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Services\Audit\AuditEventPresenter;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    public function __invoke(AuditEventPresenter $presenter): Response
    {
        Gate::authorize('viewAny', AuditEvent::class);

        return Inertia::render('admin/audit/index', ['events' => AuditEvent::orderByDesc('id')->paginate(30)
            ->through(fn (AuditEvent $event): array => $presenter->present($event))]);
    }
}
