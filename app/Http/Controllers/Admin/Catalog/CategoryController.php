<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Actions\Catalog\SaveCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\SaveCategoryRequest;
use App\Models\DishCategory;
use App\Queries\Catalog\CategoryBrowseQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(CategoryBrowseQuery $query): Response
    {
        Gate::authorize('viewAny', DishCategory::class);

        return Inertia::render('admin/catalog/categories/index', [
            'categories' => $query->paginate(), 'canManage' => Gate::allows('create', DishCategory::class),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', DishCategory::class);

        return Inertia::render('admin/catalog/categories/edit', ['category' => null]);
    }

    public function store(SaveCategoryRequest $request, SaveCategory $action): RedirectResponse
    {
        $category = $action->handle($request->user(), $request->validated());

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

    public function edit(DishCategory $category): Response
    {
        Gate::authorize('update', $category);

        return Inertia::render('admin/catalog/categories/edit', ['category' => $category]);
    }

    public function update(SaveCategoryRequest $request, DishCategory $category, SaveCategory $action): RedirectResponse
    {
        $action->handle($request->user(), $request->validated(), $category);

        return redirect()->route('catalog.categories.show', $category)->with('status', 'Category updated.');
    }
}
