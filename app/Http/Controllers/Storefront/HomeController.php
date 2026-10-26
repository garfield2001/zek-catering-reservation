<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Queries\Storefront\PublicOfferingDirectory;
use App\Services\Storefront\Selection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(Request $request, PublicOfferingDirectory $directory, Selection $selection): Response
    {
        return Inertia::render('storefront/home', [
            'catering' => $directory->catering()->take(3)->values(),
            'services' => $directory->services()->take(4)->values(),
            'selectionCount' => count($selection->all($request)),
        ]);
    }
}
