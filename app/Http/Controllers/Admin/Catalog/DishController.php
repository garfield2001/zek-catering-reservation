<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Actions\Catalog\SaveDish;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\SaveDishRequest;
use App\Models\Dish;
use App\Queries\Catalog\CatalogLookupQuery;
use App\Queries\Catalog\DishBrowseQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DishController extends Controller
{
    public function index(Request $request, DishBrowseQuery $query): Response
    {
        Gate::authorize('viewAny', Dish::class);
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        return Inertia::render('admin/catalog/dishes/index', [
            'dishes' => $query->paginate($request->query('q')), 'filters' => ['q' => $request->query('q', '')],
            'canManage' => Gate::allows('create', Dish::class),
        ]);
    }

    public function create(CatalogLookupQuery $lookups): Response
    {
        Gate::authorize('create', Dish::class);

        return Inertia::render('admin/catalog/dishes/edit', [
            'dish' => null, 'categories' => $lookups->categories(), 'families' => $lookups->families(),
        ]);
    }

    public function store(SaveDishRequest $request, SaveDish $action): RedirectResponse
    {
        $dish = $action->handle($request->user(), $request->validated());

        return redirect()->route('catalog.dishes.show', $dish)->with('status', 'Dish created.');
    }

    public function show(Dish $dish): Response
    {
        Gate::authorize('view', $dish);
        $dish->load(['category', 'eligibleFamilies', 'offerings.family', 'offerings.options']);

        return Inertia::render('admin/catalog/dishes/show', [
            'dish' => $dish, 'canManage' => Gate::allows('update', $dish),
        ]);
    }

    public function edit(Dish $dish, CatalogLookupQuery $lookups): Response
    {
        Gate::authorize('update', $dish);
        $dish->load('eligibleFamilies');

        return Inertia::render('admin/catalog/dishes/edit', [
            'dish' => $dish, 'categories' => $lookups->categories($dish), 'families' => $lookups->families(),
        ]);
    }

    public function update(SaveDishRequest $request, Dish $dish, SaveDish $action): RedirectResponse
    {
        $action->handle($request->user(), $request->validated(), $dish);

        return redirect()->route('catalog.dishes.show', $dish)->with('status', 'Dish updated.');
    }
}
