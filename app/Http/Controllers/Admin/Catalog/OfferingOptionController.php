<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Actions\Catalog\SaveOfferingOption;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\SaveOfferingOptionRequest;
use App\Models\Dish;
use App\Models\DishOffering;
use App\Models\OfferingOption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OfferingOptionController extends Controller
{
    public function create(Dish $dish, DishOffering $offering): Response
    {
        Gate::authorize('create', OfferingOption::class);

        return Inertia::render('admin/catalog/options/edit', ['dish' => $dish, 'offering' => $offering, 'option' => null]);
    }

    public function store(SaveOfferingOptionRequest $request, Dish $dish, DishOffering $offering, SaveOfferingOption $action): RedirectResponse
    {
        $action->handle($request->user(), $offering, $request->validated());

        return redirect()->route('catalog.offerings.show', [$dish, $offering])->with('status', 'Serving option created.');
    }

    public function edit(Dish $dish, DishOffering $offering, OfferingOption $option): Response
    {
        Gate::authorize('update', $option);

        return Inertia::render('admin/catalog/options/edit', ['dish' => $dish, 'offering' => $offering, 'option' => $option]);
    }

    public function update(SaveOfferingOptionRequest $request, Dish $dish, DishOffering $offering, OfferingOption $option, SaveOfferingOption $action): RedirectResponse
    {
        $action->handle($request->user(), $offering, $request->validated(), $option);

        return redirect()->route('catalog.offerings.show', [$dish, $offering])->with('status', 'Serving option updated.');
    }
}
