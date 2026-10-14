<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dish;
use App\Models\DishCategory;
use App\Models\Product;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('admin/dashboard', ['setup' => [
            'categories' => Gate::allows('viewAny', Dish::class) ? DishCategory::count() : null,
            'items' => Gate::allows('viewAny', Dish::class) ? Dish::count() : null,
            'products' => Gate::allows('viewAny', Product::class) ? Product::count() : null,
        ]]);
    }
}
