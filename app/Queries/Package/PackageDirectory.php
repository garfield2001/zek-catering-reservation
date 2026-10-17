<?php

namespace App\Queries\Package;

use App\Models\PackageVersion;
use App\Models\Product;
use App\Models\ProductFamily;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class PackageDirectory
{
    public function query(ProductFamily $family, array $filters = []): Builder
    {
        $canInspectDraft = Gate::allows('create', Product::class) || Gate::allows('publish', PackageVersion::class);

        return Product::where('family_id', $family->id)
            ->with(['currentVersion:id,package_product_id,status,version_number,price_basis,base_price_minor,minimum_quantity,total_selection_count',
                'versions' => fn ($query) => $query->where('status', 'draft')->when(! $canInspectDraft, fn ($query) => $query->whereRaw('1 = 0'))])
            ->when($filters['q'] ?? null, fn ($query, $search) => $query->where('name', 'ilike', '%'.addcslashes($search, '%_\\').'%'))
            ->when(($filters['status'] ?? '') === 'active', fn ($query) => $query->where('active', true)->whereNull('archived_at'))
            ->when(($filters['status'] ?? '') === 'inactive', fn ($query) => $query->where('active', false)->whereNull('archived_at'))
            ->when(($filters['status'] ?? '') === 'archived', fn ($query) => $query->whereNotNull('archived_at'))
            ->orderBy('sort_order')->orderBy('id');
    }
}
