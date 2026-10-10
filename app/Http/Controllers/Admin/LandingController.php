<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Access\AdminLanding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LandingController extends Controller
{
    public function __invoke(Request $request, AdminLanding $landing): RedirectResponse|Response
    {
        $path = $landing->path($request->user());

        return $path ? redirect($path) : Inertia::render('admin/no-access');
    }
}
