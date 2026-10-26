<?php

namespace App\Queries\Catalog;

use App\Models\Dish;
use Illuminate\Pagination\LengthAwarePaginator;

class FoodDirectory
{
    public function search(array $filters): LengthAwarePaginator
    {
        $perPage = in_array((int) ($filters['per_page'] ?? 24), [12, 24, 48], true)
            ? (int) ($filters['per_page'] ?? 24)
            : 24;
        $sort = $filters['sort'] ?? 'name_asc';

        $query = Dish::with('category')
            ->when($filters['q'] ?? null, function ($query, $search): void {
                $pattern = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn ($q) => $q->where('name', 'ilike', $pattern)->orWhereHas('category', fn ($c) => $c->where('name', 'ilike', $pattern)));
            })
            ->when($filters['category'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
            ->when(($filters['status'] ?? '') === 'active', fn ($q) => $q->where('active', true)->whereHas('category', fn ($c) => $c->where('active', true)))
            ->when(($filters['status'] ?? '') === 'inactive', fn ($q) => $q->where(fn ($q) => $q->where('active', false)->orWhereHas('category', fn ($c) => $c->where('active', false))));

        match ($sort) {
            'name_desc' => $query->orderByDesc('name')->orderByDesc('id'),
            'recent' => $query->orderByDesc('updated_at')->orderByDesc('id'),
            'category' => $query->orderBy('category_id')->orderBy('name')->orderBy('id'),
            default => $query->orderBy('name')->orderBy('id'),
        };

        return $query->paginate($perPage)->withQueryString();
    }
}
