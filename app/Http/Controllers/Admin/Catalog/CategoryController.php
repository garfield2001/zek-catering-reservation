<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Actions\Catalog\SaveCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\SaveCategoryRequest;
use App\Models\DishCategory;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): RedirectResponse
    {
        Gate::authorize('viewAny', DishCategory::class);

        return redirect()->route('catalog.workspace');
    }

    public function create(): RedirectResponse
    {
        Gate::authorize('create', DishCategory::class);

        return redirect()->route('catalog.workspace', ['create' => 'category']);
    }

    public function store(SaveCategoryRequest $request, SaveCategory $action): RedirectResponse
    {
        $category = $action->handle($request->user(), collect($request->validated())->except(['code', 'sort_order'])->all());

        if ($request->filled('return_product_id')) {
            $product = Product::findOrFail($request->integer('return_product_id'));
            Gate::authorize('view', $product);

            return redirect()->route($product->family->behavior_type === 'main_package' ? 'catering.show' : 'packages.show', $product)->with('status', 'Category saved.');
        }
        if ($request->boolean('workspace')) {
            return redirect()->route('catalog.workspace')->with('status', 'Category created.');
        }

        return redirect()->route('catalog.categories.show', $category)->with('status', 'Category created.');
    }

    public function show(DishCategory $category): Response
    {
        Gate::authorize('view', $category);

        return Inertia::render('admin/catalog/categories/show', [
            'category' => $category, 'dishes' => $category->dishes()->orderBy('name')->paginate(20),
            'canManage' => Gate::allows('update', $category),
        ]);
    }

    public function edit(DishCategory $category): RedirectResponse
    {
        Gate::authorize('update', $category);

        return redirect()->route('catalog.workspace', ['edit_category' => $category->id]);
    }

    public function update(SaveCategoryRequest $request, DishCategory $category, SaveCategory $action): RedirectResponse
    {
        $action->handle($request->user(), $request->validated(), $category);

        if ($request->boolean('workspace')) {
            return redirect()->route('catalog.workspace')->with('status', 'Category saved.');
        }

        return redirect()->route('catalog.categories.show', $category)->with('status', 'Category updated.');
    }
}
