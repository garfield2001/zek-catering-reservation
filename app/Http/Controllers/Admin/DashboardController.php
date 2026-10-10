<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dish;
use App\Models\DishCategory;
use App\Models\DishOffering;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\ProductVariant;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('admin/dashboard', ['setup' => [
            'hasPrimary' => ProductFamily::where('is_addon', false)->whereNull('archived_at')->exists(),
            'collections' => ProductFamily::whereNull('archived_at')->count(),
            'categories' => DishCategory::count(),
            'items' => Dish::count(),
            'offerings' => DishOffering::count(),
            'products' => Product::count(),
            'variants' => ProductVariant::count(),
        ]]);
    }
}
