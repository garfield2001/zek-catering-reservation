<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Actions\Catalog\SaveOffering;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\SaveOfferingRequest;
use App\Models\Dish;
use App\Models\DishOffering;
use App\Queries\Catalog\CatalogLookupQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OfferingController extends Controller
{
    public function create(Dish $dish, CatalogLookupQuery $lookups): Response
    {
        Gate::authorize('create', DishOffering::class);

        return Inertia::render('admin/catalog/offerings/edit', [
            'dish' => $dish, 'offering' => null, 'families' => $lookups->eligibleFamilies($dish),
        ]);
    }

    public function store(SaveOfferingRequest $request, Dish $dish, SaveOffering $action): RedirectResponse
    {
        $offering = $action->handle($request->user(), $dish, $request->validated());

        return redirect()->route('catalog.offerings.show', [$dish, $offering])->with('status', 'Offering created.');
    }

    public function show(Dish $dish, DishOffering $offering): Response
    {
        Gate::authorize('view', $offering);
        $offering->load(['family', 'options']);

        return Inertia::render('admin/catalog/offerings/show', [
            'dish' => $dish, 'offering' => $offering, 'canManage' => Gate::allows('update', $offering),
        ]);
    }

    public function edit(Dish $dish, DishOffering $offering): Response
    {
        Gate::authorize('update', $offering);
        $offering->load('family');

        return Inertia::render('admin/catalog/offerings/edit', [
            'dish' => $dish, 'offering' => $offering, 'families' => [],
        ]);
    }

    public function update(SaveOfferingRequest $request, Dish $dish, DishOffering $offering, SaveOffering $action): RedirectResponse
    {
        $action->handle($request->user(), $dish, $request->validated(), $offering);

        return redirect()->route('catalog.offerings.show', [$dish, $offering])->with('status', 'Offering updated.');
    }
}
