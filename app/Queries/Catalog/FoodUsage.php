<?php

namespace App\Queries\Catalog;

use App\Models\IndividualListing;
use App\Models\Product;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Actual version/listing relationships, never availability or former assignments. */
class FoodUsage
{
    public function forIds(array $ids): array
    {
        $rows = collect();
        if (Gate::allows('viewAny', Product::class)) {
            $rows = DB::table('package_version_food_items as menu')
                ->join('package_versions as version', 'version.id', '=', 'menu.package_version_id')
                ->join('products as product', 'product.id', '=', 'version.package_product_id')
                ->join('product_families as service', 'service.id', '=', 'product.family_id')
                ->whereIn('menu.dish_id', $ids)->whereIn('version.status', ['draft', 'published'])
                ->where(fn (Builder $query) => $this->visibleVersions($query, Product::class, 'product'))
                ->get(['menu.dish_id', 'menu.is_fixed', 'version.id as version_id', 'version.version_number', 'version.status',
                    'product.id as product_id', 'product.name', 'product.current_version_id', 'service.name as service_name', 'service.is_addon'])
                ->map(fn ($row) => [...(array) $row, 'kind' => 'package', 'current' => $row->current_version_id === $row->version_id,
                    'url' => '/admin/packages/products/'.$row->product_id]);
            $advanced = DB::table('group_options as option')->join('offering_options as serving', 'serving.id', '=', 'option.offering_option_id')
                ->join('dish_offerings as offering', 'offering.id', '=', 'serving.offering_id')
                ->join('selection_groups as inclusion', 'inclusion.id', '=', 'option.group_id')
                ->join('package_versions as version', 'version.id', '=', 'inclusion.package_version_id')
                ->join('products as product', 'product.id', '=', 'version.package_product_id')
                ->join('product_families as service', 'service.id', '=', 'product.family_id')
                ->whereIn('offering.dish_id', $ids)->whereIn('version.status', ['draft', 'published'])
                ->where(fn (Builder $query) => $this->visibleVersions($query, Product::class, 'product'))
                ->get(['offering.dish_id', 'inclusion.mode', 'version.id as version_id', 'version.version_number', 'version.status',
                    'product.id as product_id', 'product.name', 'product.current_version_id', 'service.name as service_name', 'service.is_addon'])
                ->map(fn ($row) => [...(array) $row, 'kind' => 'package', 'is_fixed' => $row->mode === 'fixed', 'current' => $row->current_version_id === $row->version_id,
                    'url' => '/admin/packages/products/'.$row->product_id]);
            $rows = $rows->concat($advanced)->unique(fn ($row) => $row['dish_id'].':'.$row['version_id']);
        }
        if (Gate::allows('viewAny', IndividualListing::class)) {
            $listings = DB::table('individual_listings as listing')->join('listing_versions as version', 'version.listing_id', '=', 'listing.id')
                ->join('product_families as service', 'service.id', '=', 'listing.family_id')
                ->whereIn('listing.dish_id', $ids)->whereIn('version.status', ['draft', 'published'])
                ->where(fn (Builder $query) => $this->visibleVersions($query, IndividualListing::class, 'listing'))
                ->get(['listing.dish_id', 'listing.id as product_id', 'version.id as version_id', 'version.name', 'version.version_number', 'version.status', 'service.name as service_name', 'listing.current_version_id'])
                ->map(fn ($row) => [...(array) $row, 'kind' => 'individual', 'is_fixed' => false, 'is_addon' => true,
                    'current' => $row->current_version_id === $row->version_id, 'url' => '/admin/items/'.$row->product_id]);
            $rows = $rows->concat($listings);
        }

        return $rows->groupBy('dish_id')->map(fn ($group) => $group->values()->all())->all();
    }

    private function visibleVersions(Builder $query, string $model, string $owner): void
    {
        $query->whereColumn('version.id', $owner.'.current_version_id');
        if (Gate::allows('create', $model) || Gate::allows('publish', $model)) {
            $query->orWhere('version.status', 'draft');
        }
        if (Gate::allows('history', $model)) {
            $query->orWhere('version.status', 'published');
        }
    }
}
