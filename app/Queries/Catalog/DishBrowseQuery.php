<?php

namespace App\Queries\Catalog;

use App\Models\Dish;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DishBrowseQuery
{
    public function paginate(?string $search): LengthAwarePaginator
    {
        $search = trim((string) $search);

        return Dish::with('category')->withCount('offerings')
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q->where('name', 'ilike', '%'.addcslashes($search, '%_\\').'%')
                ->orWhere('code', 'ilike', '%'.addcslashes($search, '%_\\').'%')))
            ->orderBy('name')->paginate(20)->withQueryString();
    }
}
