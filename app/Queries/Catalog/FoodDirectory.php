<?php

namespace App\Queries\Catalog;

use App\Models\Dish;
use Illuminate\Pagination\LengthAwarePaginator;

class FoodDirectory
{
    public function search(array $filters): LengthAwarePaginator
    {
        return Dish::with('category')
            ->when($filters['q'] ?? null, function ($query, $search): void {
                $pattern = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn ($q) => $q->where('name', 'ilike', $pattern)->orWhereHas('category', fn ($c) => $c->where('name', 'ilike', $pattern)));
            })
            ->when($filters['category'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
            ->when(($filters['status'] ?? '') === 'active', fn ($q) => $q->where('active', true)->whereHas('category', fn ($c) => $c->where('active', true)))
            ->when(($filters['status'] ?? '') === 'inactive', fn ($q) => $q->where(fn ($q) => $q->where('active', false)->orWhereHas('category', fn ($c) => $c->where('active', false))))
            ->orderBy('name')->orderBy('id')->paginate(24)->withQueryString();
    }
}
