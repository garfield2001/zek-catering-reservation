<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Dish;
use App\Models\DishCategory;
use App\Models\MeasurementUnit;
use App\Models\ProductFamily;
use App\Queries\Catalog\DeactivationImpact;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class DeactivationImpactController extends Controller
{
    public function __invoke(string $kind, int $id, DeactivationImpact $impact): JsonResponse
    {
        $class = match ($kind) {
            'food' => Dish::class, 'category' => DishCategory::class,
            'unit' => MeasurementUnit::class, 'service' => ProductFamily::class,
            default => abort(404),
        };
        Gate::authorize($kind === 'service' ? 'manage' : 'create', $class);
        $record = $class::findOrFail($id);

        return response()->json($impact->forRecord($record))->header('Cache-Control', 'private, no-store');
    }
}
