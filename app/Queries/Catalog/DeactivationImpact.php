<?php

namespace App\Queries\Catalog;

use App\Models\Dish;
use App\Models\DishCategory;
use App\Models\MeasurementUnit;
use App\Models\PackageVersion;
use App\Models\Product;
use App\Models\ProductFamily;
use Illuminate\Support\Facades\Gate;

class DeactivationImpact
{
    public function forRecord(Dish|DishCategory|MeasurementUnit|ProductFamily $record): array
    {
        $usage = [];
        if ($record instanceof ProductFamily) {
            $messages = [$record->products()->count().' packages and '.$record->listings()->count().' individual listings belong to this service.',
                'Disabling the service blocks new configuration and selection through it. Child enabled flags are not changed. Re-enable the service to restore access, subject to each item’s own availability.',
                'Archiving is permanent and abandons its open package and listing Drafts. Published Versions and history remain saved.'];
        } elseif ($record instanceof MeasurementUnit) {
            $messages = ['Disabling this unit prevents its use in new or edited serving configurations. Existing Published Version measurements and prices remain frozen. Re-enable the unit before saving a Draft that still uses it.'];
            if (Gate::allows('viewAny', Product::class) && (Gate::allows('create', Product::class) || Gate::allows('publish', PackageVersion::class))) {
                $usage = PackageVersion::with('product')->where('status', 'draft')
                    ->where(fn ($q) => $q->where('default_serving_unit_id', $record->id)->orWhereHas('sizeGroups', fn ($sizes) => $sizes->where('measurement_unit_id', $record->id)))
                    ->get()->map(fn ($version) => ['kind' => 'package', 'version_id' => $version->id, 'name' => $version->product->name,
                        'status' => $version->status, 'url' => '/admin/packages/products/'.$version->package_product_id])->all();
            }
        } else {
            $ids = $record instanceof Dish ? [$record->id] : $record->dishes()->pluck('id')->all();
            $usage = collect(app(FoodUsage::class)->forIds($ids))->flatten(1)->unique(fn ($row) => $row['kind'].':'.$row['version_id'])->values()->all();
            $messages = [$record instanceof DishCategory ? count($ids).' Food Items currently belong to this category. Their enabled flags will not change.' : 'This Food Item becomes unavailable for new selection. Its offerings and Serving Options keep their own enabled flags.',
                'Menus that require affected foods or categories may become unavailable; optional choices are reduced. Individual listings using affected foods become unavailable. Re-enable the source or revise affected configurations before offering them again.',
                'Published snapshots, prices and historical records are not rewritten. The links below show only usage you have permission to view; they are not a guarantee that every affected menu remains feasible.'];
            if ($record instanceof DishCategory) {
                // Historical category identity is frozen, even if the food is moved later.
                $messages[] = 'Published menus can still depend on this category after a Food Item is moved elsewhere. Required-category and frozen-category availability checks remain in force.';
            }
        }

        return ['messages' => $messages, 'usage' => $usage];
    }
}
