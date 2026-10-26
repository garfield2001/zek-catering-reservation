<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Actions\Catalog\EnsurePrimaryService;
use App\Http\Controllers\Controller;
use App\Models\Dish;
use App\Models\DishCategory;
use App\Queries\Catalog\CatalogLookupQuery;
use App\Queries\Catalog\FoodDirectory;
use App\Queries\Catalog\FoodUsage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FoodCatalogController extends Controller
{
    public function __invoke(Request $request, CatalogLookupQuery $lookups, EnsurePrimaryService $primary, FoodDirectory $directory, FoodUsage $usage): Response
    {
        Gate::authorize('viewAny', Dish::class);
        $primary->handle();
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'category' => ['nullable', 'integer'], 'status' => ['nullable', 'in:active,inactive'], 'sort' => ['nullable', 'in:name_asc,name_desc,recent,category'], 'per_page' => ['nullable', 'integer', 'in:12,24,48'], 'create' => ['nullable', 'in:food,category'], 'edit_food' => ['nullable', 'integer', 'exists:dishes,id'], 'edit_category' => ['nullable', 'integer', 'exists:dish_categories,id']]);

        $editor = null;
        if ($request->filled('edit_food')) {
            $item = Dish::with(['category'])->findOrFail($request->integer('edit_food'));
            Gate::authorize('update', $item);
            $editor = ['kind' => 'food', 'item' => $item];
        } elseif ($request->filled('edit_category')) {
            $item = DishCategory::findOrFail($request->integer('edit_category'));
            Gate::authorize('update', $item);
            $editor = ['kind' => 'category', 'item' => $item];
        } elseif ($request->filled('create')) {
            Gate::authorize('create', $request->input('create') === 'food' ? Dish::class : DishCategory::class);
            $editor = ['kind' => $request->input('create'), 'item' => null];
        }

        $items = $directory->search($filters);
        $usedIn = $usage->forIds($items->getCollection()->pluck('id')->all());

        return Inertia::render('admin/catalog/workspace', [
            'items' => $items, 'usedIn' => $usedIn,
            'categories' => DishCategory::withCount('dishes')->orderBy('sort_order')->orderBy('name')->get(),
            'families' => $lookups->families(), 'filters' => $filters, 'initialEditor' => $editor,
            'canManage' => Gate::allows('create', Dish::class),
        ]);
    }
}
