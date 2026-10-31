<?php

namespace App\Queries\Catalog;

use App\Models\Dish;
use App\Models\DishCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class FoodDirectory
{
    public function search(array $filters): LengthAwarePaginator
    {
        $perPageValue = (string) ($filters['per_page'] ?? '5');
        $perPage = in_array($perPageValue, ['5', '10', '30', '50', '12', '24', '48'], true)
            ? (int) $perPageValue
            : 5;
        $sort = $filters['sort'] ?? 'name';
        $direction = $filters['direction'] ?? 'asc';

        $query = Dish::with('category')
            ->when($filters['q'] ?? null, function ($query, $search): void {
                $pattern = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn ($q) => $q->where('name', 'ilike', $pattern)->orWhereHas('category', fn ($c) => $c->where('name', 'ilike', $pattern)));
            })
            ->when($filters['category'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
            ->when(($filters['status'] ?? '') === 'active', fn ($q) => $q->where('active', true)->whereHas('category', fn ($c) => $c->where('active', true)))
            ->when(($filters['status'] ?? '') === 'inactive', fn ($q) => $q->where(fn ($q) => $q->where('active', false)->orWhereHas('category', fn ($c) => $c->where('active', false))));

        match ($sort) {
            // Retain the previous query-string forms for shared links.
            'name_desc' => $query->orderByDesc('name')->orderByDesc('id'),
            'recent' => $query->orderByDesc('updated_at')->orderByDesc('id'),
            'category' => $this->orderByCategory($query, $direction),
            'availability' => $this->orderByAvailability($query, $direction),
            default => $direction === 'desc'
                ? $query->orderByDesc('name')->orderByDesc('id')
                : $query->orderBy('name')->orderBy('id'),
        };

        if ($perPageValue === 'all') {
            // This is an administrator-owned master catalog. Count first so
            // "All" remains a true, server-side filtered result rather than
            // silently limiting a browser-local page.
            $perPage = max(1, $query->count());
        }

        return $query->paginate($perPage)->withQueryString();
    }

    private function orderByCategory(Builder $query, string $direction): Builder
    {
        return $query
            ->orderBy(
                DishCategory::query()
                    ->select('name')
                    ->whereColumn('dish_categories.id', 'dishes.category_id'),
                $direction,
            )
            ->orderBy('name', $direction)
            ->orderBy('id', $direction);
    }

    private function orderByAvailability(Builder $query, string $direction): Builder
    {
        return $query
            ->orderByRaw(
                "dishes.active AND EXISTS (SELECT 1 FROM dish_categories WHERE dish_categories.id = dishes.category_id AND dish_categories.active) {$direction}",
            )
            ->orderBy('name')
            ->orderBy('id');
    }
}
