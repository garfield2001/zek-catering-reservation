<?php

namespace App\Queries\Catalog;

use App\Models\DishCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CategoryBrowseQuery
{
    public function paginate(): LengthAwarePaginator
    {
        return DishCategory::withCount('dishes')->orderBy('sort_order')->orderBy('name')->paginate(20);
    }
}
