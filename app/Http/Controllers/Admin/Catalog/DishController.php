<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Actions\Catalog\SaveDish;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\SaveDishRequest;
use App\Models\Dish;
use App\Models\OfferingOption;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DishController extends Controller
{
    public function index(): RedirectResponse
    {
        Gate::authorize('viewAny', Dish::class);

        return redirect()->route('catalog.workspace');
    }

    public function create(): RedirectResponse
    {
        Gate::authorize('create', Dish::class);

        return redirect()->route('catalog.workspace', ['create' => 'food']);
    }

    public function store(SaveDishRequest $request, SaveDish $action): RedirectResponse
    {
        // Authorize the return context before writing; never accept an arbitrary redirect URL.
        if ($request->boolean('return_to_editor')) {
            $product = $request->filled('return_product_id') ? Product::findOrFail($request->integer('return_product_id')) : null;
            Gate::authorize($product ? 'update' : 'create', $product ?? Product::class);
            abort_unless($product || $request->filled('editor_family_id'), 422);
        }
        $dish = $action->handle($request->user(), collect($request->validated())->except('code')->all());

        if ($request->boolean('return_to_editor')) {
            return ($product ? redirect()->route('packages.edit', $product) : redirect()->route('packages.editor', ['service' => $request->integer('editor_family_id')]))
                ->with('status', 'Food item saved. Your unsaved package configuration is retained.');
        }

        if ($request->filled('return_product_id')) {
            $product = Product::findOrFail($request->integer('return_product_id'));
            Gate::authorize('view', $product);

            return redirect()->route($product->family->behavior_type === 'main_package' ? 'catering.show' : 'packages.show', $product)->with('status', 'Food item saved.');
        }
        if ($request->boolean('workspace')) {
            return redirect()->route('catalog.workspace')->with('status', 'Food item created.');
        }

        return redirect()->route('catalog.dishes.show', $dish)->with('status', 'Food item created.');
    }

    public function show(Dish $dish): Response
    {
        Gate::authorize('view', $dish);
        $dish->load(['category', 'eligibleFamilies', 'offerings.family', 'offerings.options']);

        return Inertia::render('admin/catalog/dishes/show', [
            'dish' => $dish,
            'canManage' => Gate::allows('update', $dish),
            'setup' => [
                'hasCategory' => $dish->category !== null && (bool) $dish->category->getAttribute('active'),
                'eligibleCollections' => $dish->eligibleFamilies()->wherePivot('active', true)->count(),
                'activeOfferings' => $dish->offerings()->where('active', true)->count(),
                'activeServingOptions' => OfferingOption::query()->where('active', true)
                    ->whereHas('offering', fn ($query) => $query->where('dish_id', $dish->id)->where('active', true))->count(),
            ],
        ]);
    }

    public function edit(Dish $dish): RedirectResponse
    {
        Gate::authorize('update', $dish);

        return redirect()->route('catalog.workspace', ['edit_food' => $dish->id]);
    }

    public function update(SaveDishRequest $request, Dish $dish, SaveDish $action): RedirectResponse
    {
        $action->handle($request->user(), $request->validated(), $dish);

        if ($request->boolean('workspace')) {
            return redirect()->route('catalog.workspace')->with('status', 'Food item saved.');
        }

        return redirect()->route('catalog.dishes.show', $dish)->with('status', 'Food item updated.');
    }
}
