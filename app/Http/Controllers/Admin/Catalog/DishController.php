<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Actions\Catalog\SaveDish;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\SaveDishRequest;
use App\Models\Dish;
use App\Models\Product;
use App\Queries\Catalog\FoodUsage;
use Illuminate\Http\JsonResponse;
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

    public function store(SaveDishRequest $request, SaveDish $action): RedirectResponse|JsonResponse
    {
        // Authorize the return context before writing; never accept an arbitrary redirect URL.
        if ($request->boolean('return_to_editor')) {
            $product = $request->filled('return_product_id') ? Product::findOrFail($request->integer('return_product_id')) : null;
            Gate::authorize($product ? 'update' : 'create', $product ?? Product::class);
            abort_unless($product || $request->filled('editor_family_id'), 422);
        }
        $dish = $action->handle($request->user(), collect($request->validated())->except('code')->all());

        if ($request->expectsJson()) {
            return response()->json(['food' => $dish->load('category')], 201);
        }

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
        $dish->load('category');

        return Inertia::render('admin/catalog/dishes/show', [
            'dish' => $dish,
            'usedIn' => app(FoodUsage::class)->forIds([$dish->id])[$dish->id] ?? [],
            'canManage' => Gate::allows('update', $dish),
        ]);
    }

    public function edit(Dish $dish): RedirectResponse
    {
        Gate::authorize('update', $dish);

        return redirect()->route('catalog.workspace', ['edit_food' => $dish->id]);
    }

    public function update(SaveDishRequest $request, Dish $dish, SaveDish $action): RedirectResponse|JsonResponse
    {
        $dish = $action->handle($request->user(), $request->validated(), $dish);

        if ($request->expectsJson()) {
            return response()->json(['food' => $dish->load('category')]);
        }

        if ($request->boolean('workspace')) {
            return redirect()->route('catalog.workspace')->with('status', 'Food item saved.');
        }

        return redirect()->route('catalog.dishes.show', $dish)->with('status', 'Food item updated.');
    }
}
